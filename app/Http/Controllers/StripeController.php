<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Models\StripeWebhookEvent;
use App\Services\Notification\NotificationService;
use App\Services\Order\OrderService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\PaymentIntent;
use Stripe\Webhook;

class StripeController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected ?NotificationService $notificationService = null
    ) {}

    public function webhook(Request $request): Response
    {
        $endpointSecret = config('services.stripe.webhook_secret');

        if (! $endpointSecret) {
            Log::warning('Stripe webhook received but no webhook secret configured.');

            return response('Webhook secret not configured on server.', 500);
        }

        // The signature is computed over the exact bytes Stripe sent, so
        // verification has to run against the untouched body: re-encoding the
        // decoded JSON would change the bytes and make a genuine delivery look
        // forged. `getContent()` is the raw body, independent of whatever the
        // JSON middleware already decoded.
        $payload = $request->getContent();
        $signatureHeader = (string) $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $endpointSecret);
        } catch (UnexpectedValueException $e) {
            Log::error('Invalid Stripe webhook payload.', ['error' => $e->getMessage()]);

            return response('Invalid webhook payload.', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature.', ['error' => $e->getMessage()]);

            return response('Invalid webhook signature.', 400);
        } catch (\InvalidArgumentException $e) {
            Log::error('Invalid Stripe webhook request.', ['error' => $e->getMessage()]);

            return response('Invalid webhook request.', 400);
        }

        if (empty($event->id)) {
            Log::error('Stripe webhook event carries no id, so it cannot be deduplicated.');

            return response('Malformed webhook event.', 400);
        }

        $paymentIntent = $event->data->object ?? null;

        if (($paymentIntent->object ?? null) !== 'payment_intent') {
            // Acknowledged without side effects and without a ledger row: there
            // is nothing to apply, and writing one row per unrelated Stripe
            // event would grow the table for no benefit. Returning 200 is what
            // stops Stripe redelivering events this endpoint never consumes.
            return response('Unsupported event type.', 200);
        }

        // Idempotency ledger. `record()` inserts through Eloquent so PrefixedId
        // generates the `_id` primary key, and the unique index on `event_id`
        // collapses a concurrent replay onto the same row. The row is committed
        // before any order is touched, so a crash mid-processing leaves an
        // unstamped row (retryable) rather than no record at all.
        $record = StripeWebhookEvent::record((string) $event->id, (string) $event->type);

        if ($record->isProcessed()) {
            return response('Event already processed.', 200);
        }

        $lock = Cache::lock("stripe:webhook:{$event->id}", 60);

        try {
            $acquired = $lock->block(5);
        } catch (LockTimeoutException $e) {
            $acquired = false;
        }

        if (! $acquired) {
            Log::warning('Stripe webhook lock not acquired; asking Stripe to retry.', [
                'event_id' => $event->id,
                'type' => $event->type,
            ]);

            // Deliberately a 5xx, and deliberately not falling through to the
            // unlocked path: the holder of the lock may still fail, and a 200
            // here would acknowledge an event nobody ever applied, leaving the
            // order unverified with no retry coming.
            return response('Event is being processed; retry later.', 500);
        }

        try {
            $applied = DB::transaction(function () use ($record, $event, $paymentIntent): bool {
                return $this->applyStripeEvent($record, $event, $paymentIntent);
            });
        } catch (UnexpectedValueException $e) {
            // The delivery is authentic but does not reconcile with our books —
            // amount, currency or metadata disagree with the order. That is a
            // real failure, not an ignorable one, so it stays a 5xx: Stripe
            // retries, the row is left unstamped and the mismatch keeps alerting.
            Log::error('Stripe webhook failed reconciliation against the order.', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            return response('Webhook reconciliation failed.', 500);
        } catch (\Throwable $e) {
            // The services called below throw plain \Exception (insufficient
            // stock, unavailable variant) and InvalidOrderStatusException /
            // InvalidOrderTransitionException, none of which is a reason to
            // tell Stripe the event was handled. Their render() handlers would
            // answer a webhook with a redirect, so nothing is allowed to escape
            // this catch. The transaction has rolled back, which leaves the
            // ledger row unstamped for the retry.
            Log::error('Stripe webhook handling failed.', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Webhook processing failed.', 500);
        } finally {
            $lock->release();
        }

        if (! $applied) {
            return response('Event already processed.', 200);
        }

        return response('Event processed successfully.', 200);
    }

    /**
     * Apply one verified event and stamp the ledger row, atomically.
     *
     * The stamp is written inside the same transaction as the order mutations,
     * so a committed row always means the side effects are durable and a rolled
     * back delivery always leaves a replayable one. Returns false when the row
     * was already stamped, i.e. when a concurrent delivery got there first and
     * nothing was applied here.
     */
    protected function applyStripeEvent(StripeWebhookEvent $record, object $event, object $paymentIntent): bool
    {
        $lockedRecord = StripeWebhookEvent::query()
            ->whereKey($record->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedRecord->isProcessed()) {
            return false;
        }

        $this->dispatchStripeEvent($event, $paymentIntent);

        $lockedRecord->markProcessed();

        return true;
    }

    /**
     * Route a verified payment_intent event to the handler that owns it.
     *
     * An unrecognised type is a deliberate no-op: there are no side effects to
     * apply, and the caller still stamps the ledger and acknowledges the
     * delivery. Treating it as a failure instead would have Stripe redelivering
     * an event this application has no interest in for three days.
     */
    protected function dispatchStripeEvent(object $event, object $paymentIntent): void
    {
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($paymentIntent);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($paymentIntent);
                break;

            case 'payment_intent.requires_action':
                $this->handlePaymentIntentRequiresAction($paymentIntent);
                break;

            case 'payment_intent.canceled':
                $this->handlePaymentIntentCanceled($paymentIntent);
                break;

            case 'charge.refunded':
                $this->handleChargeRefunded($event, $paymentIntent);
                break;

            default:
                Log::info('Unhandled Stripe event type; acknowledged without side effects.', [
                    'event_id' => $event->id,
                    'type' => $event->type,
                ]);
        }
    }

    public function success(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->authorizeOrder($request, $order);

        if ($order->payment_method === 'online') {
            // A cancelled order must never be offered for payment again.
            if ($order->status === OrderStatus::Cancelled) {
                return view('checkout.success', compact('order'));
            }

            $paymentMethod = PaymentMethod::where('code', 'online')->first();

            // If PaymentIntent already succeeded, show success immediately
            if ($order->stripe_payment_intent_id && $paymentMethod && $paymentMethod->stripe_enabled && $paymentMethod->stripe_secret_key) {
                try {
                    $paymentIntent = PaymentIntent::retrieve($order->stripe_payment_intent_id, [
                        'api_key' => $paymentMethod->stripe_secret_key,
                    ]);

                    if ($paymentIntent->status === PaymentIntent::STATUS_SUCCEEDED && $order->payment_status !== PaymentStatus::Paid) {
                        // Update order status and redirect to success
                        DB::transaction(function () use ($order) {
                            $lockedOrder = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                            if ($lockedOrder->payment_status !== PaymentStatus::Paid) {
                                $previousStatus = $lockedOrder->status->value;
                                $changes = ['payment_status' => PaymentStatus::Paid->value];
                                if ($lockedOrder->status === OrderStatus::Pending) {
                                    $changes['status'] = OrderStatus::Confirmed->value;
                                }
                                $lockedOrder->update($changes);
                                if ($previousStatus !== $lockedOrder->status->value) {
                                    OrderStatusHistory::create([
                                        'order_id' => $lockedOrder->getKey(),
                                        'from_status' => $previousStatus,
                                        'to_status' => $lockedOrder->status->value,
                                        'note' => 'Payment confirmed (PaymentIntent already succeeded).',
                                    ]);
                                }
                            }
                        });

                        return redirect()->route('checkout.success', $order->order_number)
                            ->with('success', 'Payment was already completed. Your order is confirmed.');
                    }
                } catch (ApiErrorException $e) {
                    Log::warning('Failed to retrieve PaymentIntent for status check.', [
                        'order_id' => $order->getKey(),
                        'payment_intent_id' => $order->stripe_payment_intent_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Show payment form if payment still pending
            if ($order->payment_status !== PaymentStatus::Paid && $paymentMethod && $paymentMethod->stripe_enabled && $order->stripe_payment_intent_id && $order->stripe_client_secret) {
                return view('checkout.stripe-payment', [
                    'order' => $order,
                    'stripePublishableKey' => $paymentMethod->stripe_publishable_key,
                    'clientSecret' => $order->stripe_client_secret,
                ]);
            }

            return view('checkout.success', compact('order'))
                ->with('error', 'Online payment is not available for this order. Please contact support.');
        }

        return view('checkout.success', compact('order'));
    }

    public function status(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->authorizeOrder($request, $order);
        $this->verifyOrderRelationship($request, $order);

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
        ]);
    }

    public function cancel(Request $request, string $orderNumber): RedirectResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->authorizeOrder($request, $order);

        if ($order->payment_method === 'online' && $order->payment_status !== PaymentStatus::Paid) {
            $paymentMethod = PaymentMethod::where('code', 'online')->first();

            if ($order->stripe_payment_intent_id && $paymentMethod && $paymentMethod->stripe_enabled && $paymentMethod->stripe_secret_key) {
                // The order is only cancelled locally once Stripe can no longer
                // take money for it. If the intent already succeeded (the
                // webhook has not landed yet) or cannot be cancelled, cancelling
                // the order here would leave a charged customer with a
                // cancelled order.
                try {
                    $paymentIntent = PaymentIntent::retrieve($order->stripe_payment_intent_id, [
                        'api_key' => $paymentMethod->stripe_secret_key,
                    ]);

                    if ($paymentIntent->status === PaymentIntent::STATUS_SUCCEEDED) {
                        return redirect()->route('checkout.success', $order->order_number)
                            ->with('error', 'Your payment has already been received, so this order can no longer be cancelled. It will be confirmed shortly.');
                    }

                    if ($paymentIntent->status !== PaymentIntent::STATUS_CANCELED) {
                        $paymentIntent->cancel([
                            'cancellation_reason' => PaymentIntent::CANCELLATION_REASON_REQUESTED_BY_CUSTOMER,
                        ], [
                            'api_key' => $paymentMethod->stripe_secret_key,
                            'idempotency_key' => "order-cancel-{$order->getKey()}",
                        ]);
                    }
                } catch (ApiErrorException $e) {
                    Log::warning('Stripe PaymentIntent cancellation failed.', [
                        'order_id' => $order->getKey(),
                        'payment_intent_id' => $order->stripe_payment_intent_id,
                        'error' => $e->getMessage(),
                    ]);

                    return redirect()->route('checkout.success', $order->order_number)
                        ->with('error', 'We could not cancel your payment right now. Please try again in a moment.');
                }
            }
        }

        $cancellableStatuses = [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
        ];

        if (in_array($order->status, $cancellableStatuses, true) && $order->payment_status !== PaymentStatus::Paid) {
            try {
                $this->orderService->cancel($order->getKey(), 'Customer cancelled online payment.');
            } catch (\Throwable $e) {
                Log::error('Failed to cancel order after payment cancellation.', [
                    'order_id' => $order->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('checkout.success', $order->order_number)
            ->with('error', 'Payment was cancelled. Please try again or choose a different payment method.');
    }

    protected function handlePaymentIntentSucceeded(object $paymentIntent): void
    {
        $order = $this->findOrderForPaymentIntent($paymentIntent);

        if (! $order) {
            return;
        }

        $this->assertPaymentIntentMatchesOrder($paymentIntent, $order);

        if ($order->payment_status !== PaymentStatus::Paid && $order->status === OrderStatus::Cancelled) {
            // Money was taken for an order that no longer exists; nothing
            // here may revive it, so surface it for a manual refund.
            Log::error('Stripe payment succeeded for a cancelled order; manual refund required.', [
                'order_id' => $order->getKey(),
                'payment_intent_id' => $paymentIntent->id,
            ]);
            if ($this->notificationService) {
                $this->notificationService->sendToAllAdmins(
                    title: 'Payment succeeded for cancelled order',
                    message: "Order {$order->order_number} was charged but is cancelled - manual refund required.",
                    data: ['order_id' => $order->getKey()]
                );
            }

            return;
        }

        if ($order->payment_status === PaymentStatus::Paid || $order->status === OrderStatus::Cancelled) {
            Log::info('Stripe succeeded event ignored for finalized order.', [
                'order_id' => $order->getKey(),
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return;
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status === PaymentStatus::Paid || $lockedOrder->status === OrderStatus::Cancelled) {
                return;
            }

            $previousStatus = $lockedOrder->status->value;
            $changes = ['payment_status' => PaymentStatus::Paid->value];

            if ($lockedOrder->status === OrderStatus::Pending) {
                $changes['status'] = OrderStatus::Confirmed->value;
            }

            $lockedOrder->update($changes);

            if ($previousStatus !== $lockedOrder->status->value) {
                OrderStatusHistory::create([
                    'order_id' => $lockedOrder->getKey(),
                    'from_status' => $previousStatus,
                    'to_status' => $lockedOrder->status->value,
                    'note' => 'Payment confirmed by Stripe webhook.',
                ]);
            }
        });

        Log::info('Stripe payment succeeded.', [
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
            'currency' => $paymentIntent->currency,
        ]);
    }

    protected function handlePaymentIntentFailed(object $paymentIntent): void
    {
        $order = $this->findOrderForPaymentIntent($paymentIntent);

        if (! $order) {
            return;
        }

        $this->assertPaymentIntentMatchesOrder($paymentIntent, $order);

        if ($order->payment_status === PaymentStatus::Paid || $order->status === OrderStatus::Cancelled) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status === PaymentStatus::Paid || $lockedOrder->status === OrderStatus::Cancelled) {
                return;
            }

            // The order is deliberately NOT cancelled here. `payment_failed`
            // fires for every declined attempt, and the PaymentIntent goes
            // back to `requires_payment_method`: the payment page re-enables
            // its button so the shopper can try another card on the same
            // intent. Cancelling (and restocking) on the first decline made a
            // successful retry charge the customer for an order that was
            // already cancelled, which `payment_intent.succeeded` then refuses
            // to touch. Releasing the order is the job of the explicit cancel
            // action and of `payment_intent.canceled`, which are terminal.
            $lockedOrder->update(['payment_status' => PaymentStatus::Failed->value]);
        });

        Log::warning('Stripe payment failed.', [
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'payment_intent_id' => $paymentIntent->id,
            'last_payment_error' => $paymentIntent->last_payment_error?->message ?? 'Unknown error',
        ]);
    }

    protected function handlePaymentIntentRequiresAction(object $paymentIntent): void
    {
        $order = $this->findOrderForPaymentIntent($paymentIntent);

        if (! $order) {
            return;
        }

        $this->assertPaymentIntentMatchesOrder($paymentIntent, $order);

        Log::info('Stripe payment requires action.', [
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'payment_intent_id' => $paymentIntent->id,
        ]);
    }

    protected function handlePaymentIntentCanceled(object $paymentIntent): void
    {
        $order = $this->findOrderForPaymentIntent($paymentIntent);

        if (! $order) {
            return;
        }

        $this->assertPaymentIntentMatchesOrder($paymentIntent, $order);

        if ($order->payment_status === PaymentStatus::Paid || $order->status === OrderStatus::Cancelled) {
            return;
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status === PaymentStatus::Paid || $lockedOrder->status === OrderStatus::Cancelled) {
                return;
            }

            $lockedOrder->update(['payment_status' => PaymentStatus::Failed->value]);

            // Only cancel and restore stock if order is still in a cancellable state
            $cancellableStatuses = [
                OrderStatus::Pending->value,
                OrderStatus::Confirmed->value,
            ];

            if (in_array($lockedOrder->status->value, $cancellableStatuses, true)) {
                $this->orderService->cancel($lockedOrder->getKey(), 'Stripe PaymentIntent was canceled.');
            }
        });

        Log::info('Stripe payment canceled.', [
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'payment_intent_id' => $paymentIntent->id,
        ]);
    }

    protected function findOrderForPaymentIntent(object $paymentIntent): ?Order
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (! $order) {
            Log::warning('Stripe PaymentIntent event has no matching order.', [
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return null;
        }

        if ((string) ($paymentIntent->metadata['order_id'] ?? '') !== (string) $order->getKey()) {
            throw new UnexpectedValueException('Stripe PaymentIntent metadata does not match the order.');
        }

        return $order;
    }

    protected function assertPaymentIntentMatchesOrder(object $paymentIntent, Order $order): void
    {
        $expectedCurrency = strtoupper((string) ($order->currency ?: currency()));

        $multiplier = in_array($expectedCurrency, config('app.currencies.zero_decimal'), true) ? 1 : 100;
        $expectedAmount = (int) round((float) $order->grand_total * $multiplier);

        // Stripe reports the currency in lower case, so both sides are
        // upper-cased: comparing a lower-cased intent currency with the
        // upper-cased expectation could never match, which made every
        // genuine webhook fail reconciliation.
        if ((int) $paymentIntent->amount !== $expectedAmount || strtoupper((string) $paymentIntent->currency) !== $expectedCurrency) {
            throw new UnexpectedValueException('Stripe PaymentIntent amount or currency does not match the order.');
        }

        if ($order->payment_method !== 'online') {
            throw new UnexpectedValueException('Stripe PaymentIntent is attached to a non-online order.');
        }
    }

    protected function authorizeOrder(Request $request, Order $order): void
    {
        $user = auth()->user();

        // The order's customer (authenticated via web guard)
        if ($user && $order->customer_id && $order->customer_id === $user->customer?->_id) {
            return;
        }

        // The order's user (authenticated via web guard)
        if ($user && $order->user_id && $order->user_id === $user->_id) {
            return;
        }

        // Anyone with a valid tracking token (for guest order tracking)
        $trackingToken = $this->resolveTrackingToken($request);
        if ($order->tracking_token && $trackingToken && hash_equals($order->tracking_token, $trackingToken)) {
            return;
        }

        abort(403);
    }

    protected function resolveTrackingToken(Request $request): ?string
    {
        $token = $request->query('tracking_token', $request->cookie('guest_order_token'));

        // `?tracking_token[]=x` yields an array, which would TypeError out of
        // the `?string` return type (and out of hash_equals) as a 500.
        return is_string($token) ? $token : null;
    }

    protected function verifyOrderRelationship(Request $request, Order $order): void
    {
        $user = auth()->user();

        // Authenticated users must have a direct relationship to the order,
        // or present a valid tracking token for a tracked (guest) order.
        if ($user) {
            $isCustomer = $order->customer_id && $order->customer_id === $user->customer?->_id;
            $isUser = $order->user_id && $order->user_id === $user->_id;

            $trackingToken = $this->resolveTrackingToken($request);
            $hasTrackingToken = $order->tracking_token && $trackingToken
                && hash_equals($order->tracking_token, $trackingToken);

            abort_if(! $isCustomer && ! $isUser && ! $hasTrackingToken, 403);

            return;
        }

        // Guest: tracking token required
        $trackingToken = $this->resolveTrackingToken($request);
        abort_if(
            ! $order->tracking_token || ! $trackingToken || ! hash_equals($order->tracking_token, $trackingToken),
            403
        );
    }
}
