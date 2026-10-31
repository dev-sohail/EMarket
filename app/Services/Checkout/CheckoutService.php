<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderCharge;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Affiliate\AffiliateService;
use App\Services\Analytics\AnalyticsService;
use App\Services\Coupon\CouponService;
use App\Services\Inventory\InventoryService;
use App\Services\Notification\NotificationService;
use App\Services\Order\OrderService;
use App\Services\Payment\CircuitBreaker;
use App\Services\Pricing\PricingService;
use App\Services\Shipping\ShippingService;
use App\Services\Tax\TaxService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

class CheckoutService
{
    public function __construct(
        protected PricingService $pricingService,
        protected InventoryService $inventoryService,
        protected CouponService $couponService,
        protected NotificationService $notificationService,
        protected OrderService $orderService,
        protected ShippingService $shippingService,
        protected TaxService $taxService,
        protected CircuitBreaker $circuitBreaker = new CircuitBreaker('stripe'),
    ) {}

    public function process(array $data): Order
    {
        $lock = Cache::lock("checkout:cart:{$data['cart_id']}", 15);

        // block() THROWS LockTimeoutException instead of returning false, so an
        // unacquirable lock has to be caught here or it escapes as the generic
        // "We encountered an error while processing your order" page. The lock
        // is released in the finally block below; an expired lock is reclaimed by
        // DatabaseLock on its own `expiration` check, so there is deliberately no
        // forceRelease() here — force-releasing would hand the same cart to two
        // concurrent submissions.
        try {
            $lock->block(5);
        } catch (LockTimeoutException $e) {
            throw new \InvalidArgumentException(
                'A checkout for this cart is already in progress. Please wait a moment and try again.',
                0,
                $e
            );
        }

        try {
            $createdOrder = false;

            $order = DB::transaction(function () use ($data, &$createdOrder) {
                $cart = Cart::with(['items.product.images', 'items.variant.product.images', 'items.variant.images'])->lockForUpdate()->find($data['cart_id']);

                if (! $cart) {
                    // The cart is deleted in the same transaction that creates
                    // the order. A double-submit that was queued behind the
                    // cart lock therefore finds no cart; if the first
                    // submission produced an order, hand that order back
                    // instead of failing a checkout that actually succeeded.
                    $placedOrder = $this->findOrderForConsumedCart($data);

                    if (! $placedOrder) {
                        throw new \InvalidArgumentException('Your cart is empty.');
                    }

                    return $placedOrder->load(['items', 'shippingAddress', 'billingAddress', 'charges']);
                }

                $existingOrder = $this->findExistingOrder($data, $cart);

                if ($existingOrder) {
                    $this->logInFlightTotalMismatch($existingOrder);

                    return $existingOrder->load(['items', 'shippingAddress', 'billingAddress', 'charges']);
                }

                $this->validateCheckout($data, $cart);

                // Resolve guest customer early so coupon validation can use the resolved customer id
                if (empty($data['customer_id']) && ! empty($data['guest_email'])) {
                    $resolved = $this->resolveGuestCustomer($data);
                    if ($resolved) {
                        $data['customer_id'] = $resolved->getKey();
                    }
                }

                [$order, $pricing, $totals] = $this->createOrder($data, $cart);
                $createdOrder = true;

                $this->createOrderItems($cart, $pricing, $totals, $order);

                if (! empty($data['shipping_address'])) {
                    OrderAddress::create(array_merge(Arr::except($data['shipping_address'], ['country']), [
                        'order_id' => $order->getKey(),
                        'type' => 'shipping',
                    ]));
                }

                if (! empty($data['billing_address'])) {
                    OrderAddress::create(array_merge(Arr::except($data['billing_address'], ['country']), [
                        'order_id' => $order->getKey(),
                        'type' => 'billing',
                    ]));
                }

                if (! empty($totals['charges'])) {
                    foreach ($totals['charges'] as $charge) {
                        OrderCharge::create(array_merge($charge, [
                            'order_id' => $order->getKey(),
                        ]));
                    }
                }

                OrderStatusHistory::create([
                    'order_id' => $order->getKey(),
                    'from_status' => null,
                    'to_status' => OrderStatus::Pending->value,
                    'note' => 'Order placed successfully.',
                ]);

                if (! empty($data['coupon_code'])) {
                    $coupon = $this->couponService->getByCode($data['coupon_code']);

                    if ($coupon) {
                        $this->couponService->recordUsage($coupon, $order->customer_id, $order->getKey());
                    }
                }

                app(AffiliateService::class)->attributeOrder($order);

                app(AnalyticsService::class)->recordOrderConversion($order);

                $this->notificationService->sendToAllAdmins(
                    title: 'New Order Received',
                    message: "New order '{$order->order_number}' has been placed with a total of {$order->grand_total}.",
                    data: ['order_id' => $order->getKey(), 'order_number' => $order->order_number]
                );

                // Delete cart items and cart within the transaction to prevent race conditions
                $cart->items()->delete();
                $cart->delete();

                return $order->load(['items', 'shippingAddress', 'billingAddress', 'charges']);
            });

            // Initialize payment intent AFTER transaction commits and cart is deleted
            // This way if payment fails, we don't have a cart to restore, but the order is cancelled
            if ($order->payment_method === 'online') {
                try {
                    $this->initializePaymentIntent($order);
                } catch (\Throwable $e) {
                    $this->cancelFailedOrder($order, 'Stripe payment initialization failed.');

                    throw new \InvalidArgumentException('We could not initialize the payment. Your order was cancelled. Please try again.', 0, $e);
                }
            }

            return $order->load(['items', 'shippingAddress', 'billingAddress', 'charges']);
        } finally {
            $lock->release();
        }
    }

    /**
     * Price and persist an order.
     *
     * Every money figure comes from `PricingService`: the lines and the subtotal
     * from `calculateCartTotal()`, which prices the live product / variant
     * rather than the `cart_items.unit_price` snapshot, and the composition from
     * `calculateOrderTotals()`. The checkout preview runs those same two calls,
     * so the figure the customer was shown and the figure that is charged
     * cannot come from two code paths.
     *
     * Shipping is charged once: it is composed as the scalar that becomes
     * `orders.shipping_cost`, and any `shipping` entry the caller put in
     * `charges` is dropped as a restatement of the same fee.
     *
     * @return array{0: Order, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    public function createOrder(array $data, Cart $cart): array
    {
        $paymentMethod = PaymentMethod::where('code', $data['payment_method'] ?? null)->first();

        // Validate payment method is active
        if (! $paymentMethod || ! $paymentMethod->is_active) {
            throw new \InvalidArgumentException('The selected payment method is no longer available. Please choose another payment method.');
        }

        // Resolve customer first before using it
        $customerId = $data['customer_id'] ?? null;

        if (! $customerId && ! empty($data['guest_email'])) {
            $customerId = $this->resolveGuestCustomer($data)?->getKey();
        }

        $pricing = $this->pricingService->calculateCartTotal($cart);

        if ($pricing['has_unavailable']) {
            throw new \InvalidArgumentException(
                'These items in your cart are no longer available: '.implode(', ', $pricing['unavailable']).'. Please remove them to continue.'
            );
        }

        $subtotal = (float) $pricing['subtotal'];
        $discount = 0.0;
        $freeShipping = false;

        if (! empty($data['coupon_code'])) {
            $couponResult = $this->couponService->apply($data['coupon_code'], $cart, $customerId);
            $discount = (float) ($couponResult['discount'] ?? 0);
            $freeShipping = $couponResult['free_shipping'] ?? false;
        }

        $shippingCost = 0.0;
        $city = $data['shipping_address']['city'] ?? null;

        if ($city && ! empty($data['shipping_method_id']) && ! $freeShipping) {
            $shippingCost = $this->quoteShipping(
                (string) $city,
                $cart,
                (string) $data['shipping_method_id'],
                max(0.0, $subtotal - $discount)
            );
        }

        $taxableAmount = max(0.0, $subtotal - $discount);
        $tax = $taxableAmount > 0
            ? (float) $this->taxService->calculate($taxableAmount)['total_tax']
            : 0.0;

        $totals = $this->pricingService->calculateOrderTotals(
            $subtotal,
            $discount,
            $shippingCost,
            $tax,
            $data['charges'] ?? []
        );

        if ($paymentMethod->is_cod) {
            $data['payment_method'] = 'cash_on_delivery';
            $data['stripe_payment_intent_id'] = null;
            $data['stripe_client_secret'] = null;
        } elseif ($paymentMethod->code === 'online') {
            $this->validateStripeConfiguration($paymentMethod);
            $data['payment_method'] = 'online';
            $data['stripe_payment_intent_id'] = null;
            $data['stripe_client_secret'] = null;
        } else {
            $data['payment_method'] = $paymentMethod->code;
            $data['stripe_payment_intent_id'] = null;
            $data['stripe_client_secret'] = null;
        }

        $currency = strtolower(trim((string) currency()));

        if (! preg_match('/^[a-z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('The currency configuration is invalid. Please contact support.');
        }

        $order = Order::create([
            'cart_id' => $cart->getKey(),
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'customer_id' => $customerId,
            'user_id' => $data['user_id'] ?? null,
            'payment_method' => $data['payment_method'],
            'payment_status' => PaymentStatus::Pending->value,
            'currency' => $currency,
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'shipping_cost' => $totals['shipping'],
            'tax' => $totals['tax'],
            'additional_charges' => $totals['total_charges'],
            'grand_total' => $totals['grand_total'],
            'notes' => $data['notes'] ?? null,
            'coupon_code' => $data['coupon_code'] ?? null,
        ]);

        $order->stripe_payment_intent_id = $data['stripe_payment_intent_id'] ?? null;
        $order->stripe_client_secret = $data['stripe_client_secret'] ?? null;
        $order->save();

        return [$order, $pricing, $totals];
    }

    /**
     * The shipping fee for the chosen method, quoted from the live catalogue.
     *
     * The quote is taken from `ShippingService`, the single shipping pricing
     * implementation, so the fee the order is charged is the fee the customer
     * was quoted. A destination or method that is no longer offered is refused
     * rather than quietly priced at zero.
     */
    protected function quoteShipping(string $city, Cart $cart, string $shippingMethodId, float $orderValue): float
    {
        $weight = (float) $cart->items->sum(
            fn (CartItem $item) => ((float) ($item->variant?->weight ?? $item->product?->weight ?? 0)) * (int) $item->quantity
        );
        $quantity = (int) $cart->items->sum('quantity');

        $result = $this->shippingService->calculate($city, $weight, $orderValue, $quantity);

        if (! ($result['available'] ?? false)) {
            throw new \InvalidArgumentException(
                $result['message'] ?? 'No shipping methods are available for this destination.'
            );
        }

        $matched = collect($result['methods'])->firstWhere('method_id', $shippingMethodId);

        if (! $matched) {
            throw new \InvalidArgumentException('The selected shipping method is no longer available for this order. Please choose another shipping method.');
        }

        return max(0.0, (float) $matched['cost']);
    }

    /**
     * Write the order lines and deduct their stock.
     *
     * The discount and the tax are each allocated across the lines by
     * `PricingService::allocateAcrossLines()`: the discount on the gross line
     * amounts, the tax on the *net* line amounts, which is the basis
     * `TaxService` computed it on. Allocating tax on the gross basis while the
     * tax itself was computed on the net basis made the per-line tax figures
     * not sum to `orders.tax`, and `$subtotal` was an unguarded divisor, so a
     * fully discounted cart raised a DivisionByZeroError.
     *
     * `line_total` is stored as the net line amount, so
     * `Σ line_total = subtotal - discount` and
     * `Σ (line_total + tax) = subtotal - discount + tax`, leaving
     * `grand_total - Σ (line_total + tax) = shipping + additional_charges`.
     *
     * @param  array<string, mixed>  $pricing
     * @param  array<string, mixed>  $totals
     */
    protected function createOrderItems(Cart $cart, array $pricing, array $totals, Order $order): void
    {
        $lines = array_values($pricing['items'] ?? []);
        $discounts = $this->pricingService->allocateAcrossLines($lines, (float) $totals['discount']);

        $netLines = [];
        $netByItem = [];

        foreach ($lines as $index => $line) {
            $net = max(0.0, round((float) $line['line_total'] - $discounts[$index], 2));

            $netLines[] = ['line_total' => $net];
            $netByItem[$line['cart_item_id']] = $net;
        }

        $taxes = $this->pricingService->allocateAcrossLines($netLines, (float) $totals['tax']);
        $discountByItem = [];
        $taxByItem = [];

        foreach ($lines as $index => $line) {
            $discountByItem[$line['cart_item_id']] = $discounts[$index];
            $taxByItem[$line['cart_item_id']] = $taxes[$index];
        }

        foreach ($cart->items as $item) {
            $itemId = (string) $item->getKey();
            $line = null;

            foreach ($lines as $candidate) {
                if ($candidate['cart_item_id'] === $itemId) {
                    $line = $candidate;

                    break;
                }
            }

            if ($line === null) {
                continue;
            }

            $item->loadMissing(['product', 'variant']);

            OrderItem::create([
                'order_id' => $order->getKey(),
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'product_name' => $line['product_name'],
                'sku' => $item->variant?->sku ?? $item->product?->sku ?? '',
                'variant_name' => $item->variant?->name,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => $discountByItem[$itemId],
                'tax' => $taxByItem[$itemId],
                'line_total' => $netByItem[$itemId],
            ]);

            $this->inventoryService->deductStock(
                $item->product_id,
                $item->variant_id,
                $line['quantity'],
                $order->getKey(),
                // Adding the line to the cart reserved exactly this quantity.
                (int) $item->quantity
            );
        }
    }

    /**
     * Resolve — or revive — the customer a guest checkout belongs to.
     *
     * `customers.email` carries a PLAIN unique index, so a soft-deleted row
     * still occupies the address. Probing without `withTrashed()` returns null
     * and the following `create()` dies on a duplicate key, which is how guest
     * checkout broke for a returning guest whose account an admin had deleted.
     * The probe therefore has to see trashed rows, and the row it finds has to
     * be restored rather than re-created. A revival is logged so an operator
     * can see that an admin deletion was undone.
     */
    protected function resolveGuestCustomer(array $data): ?Customer
    {
        $email = trim((string) $data['guest_email']);
        $name = trim((string) ($data['guest_name'] ?? ''));
        $nameParts = $name === '' ? [] : preg_split('/\s+/u', $name, 2);

        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        $phone = $data['guest_phone'] ?? null;

        $customer = Customer::withTrashed()->where('email', $email)->first();

        if (! $customer) {
            $user = User::withTrashed()->where('email', $email)->first();
            $customer = $user?->customer()->withTrashed()->first();
        }

        if (! $customer) {
            return Customer::create(array_filter([
                'first_name' => $firstName !== '' ? $firstName : 'Guest',
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        $revivals = [];

        if ($customer->trashed()) {
            $customer->restore();
            $revivals[] = 'restored';
        }

        if (! $customer->is_active) {
            throw new \InvalidArgumentException('This customer account is not active.');
        }

        $updates = array_filter([
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'phone' => $phone,
        ], fn ($value) => $value !== null && $value !== '');

        if ($updates !== []) {
            $customer->fill($updates)->save();
        }

        if ($revivals !== []) {
            Log::info('Guest checkout reused a soft-deleted customer record.', [
                'customer_id' => $customer->getKey(),
                'actions' => $revivals,
            ]);
        }

        Log::info('Guest customer resolved.', ['customer_id' => $customer->getKey()]);

        return $customer;
    }

    /**
     * Report, without changing anything, an order whose stored total does not
     * equal the sum of its own stored components.
     *
     * `orders.grand_total` is written once, when the order is created, and is
     * never recomputed. Stripe's `assertPaymentIntentMatchesOrder()` derives
     * the expected PaymentIntent amount from that same stored value, so orders
     * created before the double-shipping fix keep matching their own
     * PaymentIntent. They must therefore NOT be re-priced here: rewriting a
     * historical total would invalidate an in-flight payment and Stripe would
     * retry the webhook until it expired. Logging the discrepancy is how an
     * operator finds the orders that need a manual refund decision.
     */
    protected function logInFlightTotalMismatch(Order $order): void
    {
        $componentsTotal = round(
            (float) $order->subtotal
                - (float) $order->discount
                + (float) $order->shipping_cost
                + (float) $order->tax
                + (float) $order->additional_charges,
            2
        );

        $difference = round((float) $order->grand_total - $componentsTotal, 2);

        if ($difference === 0.0) {
            return;
        }

        Log::warning('Order total does not equal the sum of its components. Left unchanged so in-flight payments are not invalidated.', [
            'order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'grand_total' => (float) $order->grand_total,
            'components_total' => $componentsTotal,
            'difference' => $difference,
        ]);
    }

    /**
     * Create the Stripe PaymentIntent for an order.
     *
     * The amount is always derived from the order's own stored `grand_total`,
     * which is written once at creation and never recomputed. That is
     * deliberate: an order placed before the double-shipping fix keeps its
     * historical total, so the amount already authorised with Stripe still
     * matches `StripeController::assertPaymentIntentMatchesOrder()` and the
     * customer is not stranded mid-payment. Correcting those orders is a
     * deliberate operational decision (refund or re-charge), not something this
     * path may do silently.
     */
    public function initializePaymentIntent(Order $order): void
    {
        if ($order->payment_method !== 'online') {
            return;
        }

        if ($order->stripe_payment_intent_id && $order->stripe_client_secret) {
            return;
        }

        $paymentMethod = PaymentMethod::where('code', 'online')->first();

        if (! $paymentMethod) {
            throw new \InvalidArgumentException('Online payment is not available at the moment. Please choose another payment method or contact support.');
        }

        $this->validateStripeConfiguration($paymentMethod);

        $currency = $order->currency;

        if (! preg_match('/^[a-z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('The currency configuration is invalid. Please contact support.');
        }

        // Zero-decimal currencies don't need multiplication by 100
        if (in_array(strtoupper($currency), config('app.currencies.zero_decimal'), true)) {
            $amountInCents = (int) round((float) $order->grand_total);
        } else {
            $amountInCents = (int) round((float) $order->grand_total * 100);
        }

        if ($amountInCents < 50) {
            throw new \InvalidArgumentException('The order total is too low for online payment. Please add more items or choose another payment method.');
        }

        // Generate and validate idempotency key (Stripe requires max 255 chars, unique per request)
        $idempotencyKey = 'checkout-'.($order->idempotency_key ?: $order->getKey());
        if (strlen($idempotencyKey) > 255) {
            $idempotencyKey = 'checkout-'.substr($order->idempotency_key ?: (string) $order->getKey(), 0, 245);
        }

        $secretKey = $paymentMethod->stripe_secret_key;

        try {
            $paymentIntent = $this->circuitBreaker->call(fn () => PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'payment_method_types' => ['card'],
                'metadata' => [
                    'order_id' => (string) $order->getKey(),
                    'order_number' => (string) $order->order_number,
                    'payment_method_id' => (string) $paymentMethod->getKey(),
                    'idempotency_key' => (string) $order->idempotency_key,
                    'sandbox' => $paymentMethod->stripe_sandbox ? 'true' : 'false',
                    'currency' => $currency,
                ],
            ], [
                'api_key' => $secretKey,
                'idempotency_key' => $idempotencyKey,
            ]));
        } catch (ApiErrorException $e) {
            throw new \InvalidArgumentException('We could not initialize the payment. Please try again or contact support if the problem persists.', 0, $e);
        }

        try {
            $order->stripe_payment_intent_id = $paymentIntent->id;
            $order->stripe_client_secret = $paymentIntent->client_secret;
            $order->save();
        } catch (\Throwable $e) {
            // Use the SAME idempotency key for cancellation to ensure idempotency
            try {
                $this->circuitBreaker->call(fn () => $paymentIntent->cancel([
                    'cancellation_reason' => PaymentIntent::CANCELLATION_REASON_ABANDONED,
                ], [
                    'api_key' => $secretKey,
                    'idempotency_key' => $idempotencyKey,
                ]));
            } catch (\Throwable $cancelError) {
                Log::error('Failed to cancel PaymentIntent after DB update failure.', [
                    'payment_intent_id' => $paymentIntent->id,
                    'order_id' => $order->getKey(),
                    'error' => $cancelError->getMessage(),
                ]);
            }

            throw new \InvalidArgumentException('We could not save the payment information. The payment has been cancelled. Please try again.', 0, $e);
        }
    }

    /**
     * Refuse to sell anything that can no longer be bought.
     *
     * A soft-deleted product or variant resolves to null on the cart's relation
     * rather than raising, and an inactive one used to sail straight through
     * and be charged. Both are now unsellable. These are customer-facing
     * messages, so they are `InvalidArgumentException`s: the checkout
     * controller surfaces that message instead of a generic failure page.
     */
    public function validateCheckout(array $data, Cart $cart): bool
    {
        if ($cart->items->isEmpty()) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        foreach ($cart->items as $item) {
            $item->loadMissing(['product', 'variant']);

            if (! $this->pricingService->isPurchasable($item->product, $item->variant, $item->variant_id)) {
                throw new \InvalidArgumentException(
                    ($item->product?->name ?? 'An item in your cart').' is no longer available. Please remove it from your cart to continue.'
                );
            }

            $inventory = $this->inventoryService->getStock($item->product_id, $item->variant_id);

            // The cart line's own reservation is part of `reserved_quantity`
            // and must not be counted against the shopper who holds it.
            $ownReservation = max(0, min((int) $item->quantity, $inventory->reserved_quantity));
            $available = $inventory->quantity - ($inventory->reserved_quantity - $ownReservation);

            if ($available < $item->quantity) {
                throw new \InvalidArgumentException("Insufficient stock for {$item->product->name}. Available: {$available}, Requested: {$item->quantity}.");
            }
        }

        if (empty($data['shipping_address'])) {
            throw new \InvalidArgumentException('Shipping address is required.');
        }

        return true;
    }

    /**
     * The live order a replayed submission for an already-converted cart maps
     * to. Only an exact idempotency-key match counts, and a cancelled order is
     * never handed back as if it had been placed.
     */
    protected function findOrderForConsumedCart(array $data): ?Order
    {
        if (empty($data['idempotency_key'])) {
            return null;
        }

        return Order::where('cart_id', $data['cart_id'])
            ->where('idempotency_key', $data['idempotency_key'])
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->first();
    }

    protected function findExistingOrder(array $data, Cart $cart): ?Order
    {
        if (! empty($data['idempotency_key'])) {
            $order = Order::where('cart_id', $cart->getKey())
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($order) {
                return $order;
            }
        }

        return Order::where('cart_id', $cart->getKey())
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Confirmed->value])
            ->where('created_at', '>=', now()->subMinutes(60))
            ->lockForUpdate()
            ->first();
    }

    protected function validateStripeConfiguration(PaymentMethod $paymentMethod): void
    {
        if (! $paymentMethod->stripe_enabled) {
            throw new \InvalidArgumentException('Online payment is currently unavailable. Please choose another payment method.');
        }

        if (! $paymentMethod->stripe_secret_key || ! $paymentMethod->stripe_publishable_key) {
            throw new \InvalidArgumentException('Online payment is not properly configured. Please contact support.');
        }

        $prefix = $paymentMethod->stripe_sandbox ? 'test' : 'live';

        if (! str_starts_with($paymentMethod->stripe_publishable_key, "pk_{$prefix}_") || ! str_starts_with($paymentMethod->stripe_secret_key, "sk_{$prefix}_")) {
            throw new \InvalidArgumentException('The Stripe keys do not match the selected environment (test/live). Please contact support.');
        }
    }

    protected function cancelFailedOrder(Order $order, string $reason): void
    {
        try {
            $this->orderService->cancel($order->getKey(), $reason);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
