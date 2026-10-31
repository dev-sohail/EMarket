<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::SECRET]);
    }

    public function test_succeeded_event_marks_the_order_paid_and_confirmed(): void
    {
        $order = $this->onlineOrder();

        $response = $this->deliver($this->event('evt_succeeded', 'payment_intent.succeeded', $order));

        $response->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
    }

    public function test_a_replayed_event_is_acknowledged_without_reapplying(): void
    {
        $order = $this->onlineOrder();
        $payload = $this->event('evt_replay', 'payment_intent.succeeded', $order);

        $this->deliver($payload)->assertOk();

        // Put the order back, then replay the same event id.
        Order::whereKey($order->getKey())->update([
            'payment_status' => PaymentStatus::Pending->value,
            'status' => OrderStatus::Pending->value,
        ]);

        $this->deliver($payload)->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(1, StripeWebhookEvent::where('event_id', 'evt_replay')->count());
    }

    public function test_a_declined_attempt_does_not_cancel_an_order_that_can_still_be_paid(): void
    {
        $order = $this->onlineOrder();

        $this->deliver($this->event('evt_failed', 'payment_intent.payment_failed', $order))->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(OrderStatus::Pending, $order->status);

        // The shopper retries with another card on the same intent.
        $this->deliver($this->event('evt_retry_ok', 'payment_intent.succeeded', $order))->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
    }

    public function test_a_canceled_intent_cancels_the_order(): void
    {
        $order = $this->onlineOrder();

        $this->deliver($this->event('evt_canceled', 'payment_intent.canceled', $order))->assertOk();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_an_amount_mismatch_is_rejected_and_leaves_the_order_alone(): void
    {
        $order = $this->onlineOrder();

        $this->deliver($this->event('evt_wrong_amount', 'payment_intent.succeeded', $order, amount: 100))
            ->assertStatus(500);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_a_bad_signature_is_rejected(): void
    {
        $order = $this->onlineOrder();
        $payload = $this->event('evt_forged', 'payment_intent.succeeded', $order);

        $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1='.str_repeat('0', 64),
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    private function onlineOrder(): Order
    {
        return Order::factory()->create([
            'payment_method' => 'online',
            'currency' => 'gbp',
            'grand_total' => 58.99,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);
    }

    private function event(string $id, string $type, Order $order, int $amount = 5899): string
    {
        return json_encode([
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => [
                'id' => 'pi_test_123',
                'object' => 'payment_intent',
                'amount' => $amount,
                'currency' => 'gbp',
                'metadata' => ['order_id' => (string) $order->getKey()],
                'last_payment_error' => ['message' => 'Your card was declined.'],
            ]],
        ]);
    }

    private function deliver(string $payload)
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET);

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
