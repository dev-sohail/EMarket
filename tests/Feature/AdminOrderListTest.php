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
 * Guards for the orders list (resources/views/admin/order/index.blade.php and
 * OrderController::index / OrderService::getAll).
 *
 * Defects this screen shipped with:
 *
 *  1. The status tabs for Pending, Processing and Shipped rendered identical
 *     markup whether or not they were the selected filter, so picking one
 *     changed nothing on screen. Only Delivered and Cancelled had a real
 *     active state.
 *  2. Five of the ten OrderStatus values had no tab at all - Confirmed, Ready
 *     to Ship, Out for Delivery, Completed and Returned orders could not be
 *     reached from the list without hand-editing the URL.
 *  3. Every status tab dropped the other filters, so searching and then
 *     clicking a tab silently discarded the search.
 *  4. The status pill was built from five conditional class expressions with no
 *     fallback, so an order in one of the unlisted statuses rendered as
 *     unstyled text.
 *  5. `per_page` reached the paginator unvalidated and uncapped, so
 *     `?per_page=100000` asked the database for the whole order table.
 *  6. The search box promises to match a customer name, but the query only
 *     looked inside the `customer` relation, while the table displays
 *     `user->name` for orders that have no customer record - so those names
 *     were shown but not searchable.
 */
class AdminOrderListTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'order-list-admin@example.test',
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

    protected function page(User $actor, array $query = []): string
    {
        return $this->actingAs($actor, 'admin')
            ->get(route('admin.orders.index', $query))
            ->assertOk()
            ->getContent();
    }

    /**
     * The status tab row only.
     */
    protected function tabBar(User $actor, array $query = []): string
    {
        preg_match('/<nav[^>]*data-tabs[^>]*>(.*?)<\/nav>/s', $this->page($actor, $query), $bar);

        return $bar[1] ?? '';
    }

    public function test_the_selected_status_tab_is_visually_distinct(): void
    {
        $admin = $this->admin();

        foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status) {
            $active = $this->tabBar($admin, ['status' => $status]);
            $all = $this->tabBar($admin);

            // Exactly one tab may claim to be current: the selected status.
            $this->assertStringContainsString(
                'aria-current="page"',
                $active,
                "the {$status} tab does not mark itself selected"
            );
            $this->assertSame(
                1,
                substr_count($active, 'aria-current="page"'),
                'more than one status tab claims to be selected'
            );

            // With no status filter only the "All" tab may claim it.
            $this->assertStringNotContainsString('status=pending" aria-current', $all);
        }
    }

    public function test_every_order_status_is_reachable_from_the_tab_row(): void
    {
        $admin = $this->admin();
        $tabs = $this->tabBar($admin);

        foreach (OrderStatus::cases() as $case) {
            $this->assertStringContainsString(
                'status='.$case->value,
                $tabs,
                "the {$case->value} status has no tab"
            );
        }
    }

    public function test_switching_status_keeps_the_other_filters(): void
    {
        $admin = $this->admin();
        $tabs = $this->tabBar($admin, ['search' => 'Zed', 'payment_status' => 'paid']);

        $this->assertStringContainsString('search=Zed', $tabs);
        $this->assertStringContainsString('payment_status=paid', $tabs);
    }

    public function test_pagination_preserves_the_filters(): void
    {
        $admin = $this->admin();

        foreach (range(1, 20) as $index) {
            Order::factory()->create([
                'order_number' => 'ORD-'.$index,
                'status' => 'pending',
                'grand_total' => 10,
            ]);
        }

        $html = $this->page($admin, ['status' => 'pending', 'search' => 'ORD']);

        // Without withQueryString() the page-2 link drops status and search, so
        // paging silently resets the operator's filters.
        $this->assertStringContainsString('status=pending', $html);
        $this->assertMatchesRegularExpression('/page=2[^"]*status=pending|status=pending[^"]*page=2/', $html);
    }

    public function test_every_status_renders_a_styled_pill(): void
    {
        $admin = $this->admin();

        foreach (OrderStatus::cases() as $case) {
            Order::factory()->create([
                'order_number' => 'ORD-'.$case->value,
                'status' => $case->value,
                'grand_total' => 10,
            ]);
        }

        $html = $this->page($admin);

        foreach (OrderStatus::cases() as $case) {
            // Each pill must carry a tone class, not just the base padding.
            $this->assertMatchesRegularExpression(
                '/data-status="'.$case->value.'"\s+class="[^"]*(?:bg-\[#[0-9A-Fa-f]{6}\]|bg-\[var\(--color-[a-z-]+\)\])/',
                $html,
                "the {$case->value} pill is unstyled"
            );
        }
    }

    public function test_an_oversized_page_size_is_rejected(): void
    {
        $admin = $this->admin();

        Order::factory()->create(['order_number' => 'ORD-1', 'grand_total' => 10]);

        // Validated at the edge, and clamped again in the service so any other
        // caller cannot ask the database for the whole table either.
        $this->actingAs($admin, 'admin')
            ->from(route('admin.orders.index'))
            ->get(route('admin.orders.index', ['per_page' => 100000]))
            ->assertRedirect()
            ->assertSessionHasErrors('per_page');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index', ['per_page' => 50]))
            ->assertOk();
    }

    public function test_the_service_clamps_a_directly_passed_page_size(): void
    {
        // Bypasses the request validation on purpose: the cap has to hold for
        // every caller, not only for the ones that pass through the form.
        foreach (range(1, 30) as $index) {
            Order::factory()->create([
                'order_number' => 'ORD-'.$index,
                'grand_total' => 10,
            ]);
        }

        $paginator = app(OrderService::class)->getAll(['per_page' => 5000]);

        $this->assertLessThanOrEqual(100, $paginator->perPage());
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.orders.index'))
            ->get(route('admin.orders.index', ['status' => 'nonsense']))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_the_search_matches_a_user_name_on_orders_without_a_customer_record(): void
    {
        $admin = $this->admin();

        $user = User::factory()->create(['name' => 'Wilhelmina Featherstonehaugh']);
        Order::factory()->create([
            'order_number' => 'ORD-USER',
            'customer_id' => null,
            'user_id' => $user->getKey(),
            'grand_total' => 10,
        ]);

        $html = $this->page($admin, ['search' => 'Featherstonehaugh']);

        $this->assertStringContainsString('ORD-USER', $html);
    }

    public function test_the_list_reports_status_counts_for_the_summary(): void
    {
        $admin = $this->admin();

        Order::factory()->create(['order_number' => 'ORD-A', 'status' => 'pending', 'grand_total' => 10]);
        Order::factory()->create(['order_number' => 'ORD-B', 'status' => 'delivered', 'grand_total' => 20]);

        $html = $this->page($admin);

        $this->assertStringContainsString('2 orders', $html);
    }

    public function test_the_payment_filter_narrows_the_list(): void
    {
        $admin = $this->admin();

        Order::factory()->create(['order_number' => 'ORD-PAID', 'payment_status' => 'paid', 'grand_total' => 10]);
        Order::factory()->create(['order_number' => 'ORD-UNPAID', 'payment_status' => 'pending', 'grand_total' => 10]);

        $html = $this->page($admin, ['payment_status' => 'paid']);

        $this->assertStringContainsString('ORD-PAID', $html);
        $this->assertStringNotContainsString('ORD-UNPAID', $html);
    }

    public function test_the_page_renders_with_no_orders(): void
    {
        $admin = $this->admin();

        $html = $this->page($admin, ['status' => 'delivered']);

        $this->assertStringContainsString('No orders', $html);
    }
}
