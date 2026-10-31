<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;

class RefundService
{
    public function refund(Order $order, ?float $amount = null): array
    {
        return DB::transaction(function () use ($order, $amount) {
            $lockedOrder = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status !== PaymentStatus::Paid) {
                throw new \Exception('Only paid orders can be refunded.');
            }

            if ($lockedOrder->status === OrderStatus::Cancelled) {
                // A cancelled order may have had payment processed before
                // cancellation; refunding is still allowed but we log it.
            }

            $grandTotal = (float) $lockedOrder->grand_total;
            $refundAmount = $amount === null ? $grandTotal : (float) $amount;

            if ($refundAmount <= 0) {
                throw new \Exception('Refund amount must be greater than 0.');
            }

            if ($refundAmount > $grandTotal) {
                throw new \Exception('Refund amount cannot exceed the order total.');
            }

            $paymentMethod = PaymentMethod::where('code', 'online')->first();

            if ($lockedOrder->stripe_payment_intent_id && $paymentMethod && $paymentMethod->stripe_enabled && $paymentMethod->stripe_secret_key) {
                try {
                    $refundData = [
                        'payment_intent' => $lockedOrder->stripe_payment_intent_id,
                        'amount' => (int) round($refundAmount * 100),
                    ];

                    $cb = new CircuitBreaker('stripe');
                    $refund = $cb->call(fn () => Refund::create($refundData, [
                        'api_key' => $paymentMethod->stripe_secret_key,
                    ]));

                    $isFull = $refundAmount >= $grandTotal - 0.0001;

                    $changes = [];
                    if ($isFull) {
                        $changes['payment_status'] = PaymentStatus::Refunded->value;
                    } else {
                        $changes['payment_status'] = PaymentStatus::PartiallyRefunded->value;
                    }

                    if (! empty($changes)) {
                        $lockedOrder->update($changes);
                    }

                    Log::info('Stripe refund created.', [
                        'order_id' => $lockedOrder->getKey(),
                        'refund_id' => $refund->id,
                        'amount' => $refundAmount,
                    ]);

                    return [
                        'success' => true,
                        'refund_id' => $refund->id,
                        'amount' => $refundAmount,
                    ];
                } catch (ApiErrorException $e) {
                    Log::error('Stripe refund failed.', [
                        'order_id' => $lockedOrder->getKey(),
                        'error' => $e->getMessage(),
                    ]);

                    throw new \Exception('Stripe refund failed: '.$e->getMessage());
                }
            }

            // If no Stripe payment intent, process offline refund
            $isFull = $refundAmount >= $grandTotal - 0.0001;

            $changes = [];
            if ($isFull) {
                $changes['payment_status'] = PaymentStatus::Refunded->value;
            } else {
                $changes['payment_status'] = PaymentStatus::PartiallyRefunded->value;
            }

            if (! empty($changes)) {
                $lockedOrder->update($changes);
            }

            Log::info('Offline refund processed.', [
                'order_id' => $lockedOrder->getKey(),
                'amount' => $refundAmount,
            ]);

            return [
                'success' => true,
                'amount' => $refundAmount,
            ];
        });
    }
}
