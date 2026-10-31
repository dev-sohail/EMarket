<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Services\Return\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Carbon 3's diffInDays() is signed, so `now()->diffInDays($deliveredAt)` was
 * negative for every past delivery and the `> window` test could never fire:
 * returns were accepted years after delivery.
 */
class ReturnWindowTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrder(int $daysAgo): Order
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered->value]);
        $orderItem = OrderItem::factory()->forOrder($order)->create(['quantity' => 2]);

        $history = OrderStatusHistory::create([
            'order_id' => $order->getKey(),
            'from_status' => OrderStatus::Shipped->value,
            'to_status' => OrderStatus::Delivered->value,
            'note' => 'Delivered.',
        ]);

        DB::table('order_status_histories')
            ->where('_id', $history->getKey())
            ->update(['created_at' => now()->subDays($daysAgo)]);

        return $order;
    }

    public function test_a_return_inside_the_window_is_accepted(): void
    {
        $order = $this->deliveredOrder(5);

        $orderItem = $order->items()->first();
        $return = app(ReturnService::class)->request([
            'order_id' => $order->getKey(),
            'reason' => 'Changed my mind',
            'items' => [
                [
                    'order_item_id' => $orderItem->getKey(),
                    'quantity' => 1,
                ],
            ],
        ]);

        $this->assertSame($order->getKey(), $return->order_id);
    }

    public function test_a_return_after_the_window_is_refused(): void
    {
        $order = $this->deliveredOrder(((int) config('app.returns.window_days', 30)) + 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('return window');

        $orderItem = $order->items()->first();
        app(ReturnService::class)->request([
            'order_id' => $order->getKey(),
            'reason' => 'Too late',
            'items' => [
                [
                    'order_item_id' => $orderItem->getKey(),
                    'quantity' => 1,
                ],
            ],
        ]);
    }
}
