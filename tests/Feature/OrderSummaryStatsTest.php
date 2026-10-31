<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for OrderService::getSummaryStats(), the figures behind the cards and
 * the status tabs on the orders list.
 *
 * Defects it shipped with:
 *
 *  1. `average_order` divided revenue — which excludes cancelled and returned
 *     orders — by the count of *all* orders. Every cancelled order silently
 *     pulled the headline average order value down.
 *  2. Counts were returned for seven hand-picked statuses, but the list renders
 *     one tab per OrderStatus case. Ready To Ship, Out For Delivery, Completed
 *     and Returned therefore showed a tab with no count on it however many
 *     orders sat in those states.
 */
class OrderSummaryStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function service(): OrderService
    {
        return app(OrderService::class);
    }

    protected function order(string $status, float $total): Order
    {
        return Order::factory()->create([
            'status' => $status,
            'grand_total' => $total,
        ]);
    }

    public function test_average_order_excludes_cancelled_and_returned_from_both_sides(): void
    {
        $this->order(OrderStatus::Delivered->value, 100);
        $this->order(OrderStatus::Shipped->value, 100);

        // Both orders in this book are worth nothing and count for nothing.
        $this->order(OrderStatus::Cancelled->value, 500);
        $this->order(OrderStatus::Returned->value, 500);

        $stats = $this->service()->getSummaryStats();

        $this->assertSame(200.0, $stats['revenue']);
        $this->assertSame(2, $stats['revenue_order_count']);
        $this->assertSame(100.0, $stats['average_order'], 'cancelled orders must not dilute the average');
    }

    public function test_total_still_counts_every_order_including_cancelled_ones(): void
    {
        $this->order(OrderStatus::Delivered->value, 100);
        $this->order(OrderStatus::Cancelled->value, 500);

        $stats = $this->service()->getSummaryStats();

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats[OrderStatus::Cancelled->value]);
    }

    public function test_every_status_case_carries_a_count(): void
    {
        foreach (OrderStatus::cases() as $case) {
            $this->order($case->value, 25);
        }

        $stats = $this->service()->getSummaryStats();

        foreach (OrderStatus::cases() as $case) {
            $this->assertArrayHasKey(
                $case->value,
                $stats,
                "no count returned for the {$case->value} tab"
            );
            $this->assertSame(1, $stats[$case->value]);
        }
    }

    public function test_an_empty_book_reports_zeroes_rather_than_dividing_by_zero(): void
    {
        $stats = $this->service()->getSummaryStats();

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0.0, $stats['revenue']);
        $this->assertSame(0.0, $stats['average_order']);
    }

    public function test_the_list_shows_a_count_on_every_status_tab_that_has_orders(): void
    {
        // The four statuses the summary used to omit.
        foreach (['ready_to_ship', 'out_for_delivery', 'completed', 'returned'] as $status) {
            Order::factory()->create([
                'order_number' => 'ORD-'.strtoupper(substr($status, 0, 4)).'-'.random_int(1000, 9999),
                'status' => $status,
                'grand_total' => 40,
            ]);
        }

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->getContent();

        preg_match('/<nav[^>]*data-tabs[^>]*>(.*?)<\/nav>/s', $html, $bar);
        $tabs = $bar[1] ?? '';

        foreach (['ready_to_ship', 'out_for_delivery', 'completed', 'returned'] as $status) {
            $this->assertMatchesRegularExpression(
                '#status='.$status.'.*?opacity-70">1</span>#s',
                $tabs,
                "the {$status} tab shows no count"
            );
        }
    }

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'summary-stats-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);
        $role->permissions()->attach(Permission::create([
            'name' => 'orders.view',
            'slug' => 'orders-view',
            'group_name' => 'orders',
        ])->getKey());

        return $admin;
    }
}
