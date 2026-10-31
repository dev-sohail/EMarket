<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the analytics overview screen
 * (resources/views/admin/analytics/overview.blade.php).
 *
 * Defects this screen has shipped before:
 *
 *  1. The period picker never reflected the live period. AnalyticsService
 *     returns 'range' as the number of days it walked, which is `span + 1`
 *     because both bounds are inclusive, so `$range == 30` never held for a
 *     30-day request and no option was marked selected.
 *  2. Top Categories divided each bar by `$topCategories->max(...)`. A period
 *     with no revenue (or only free items) left the max at 0 and raised
 *     DivisionByZeroError, taking the whole page to a 500.
 *  3. Every money figure was prefixed with a hard-coded "$" while the rest of
 *     the app formats through format_currency(), so a non-USD store showed the
 *     wrong symbol on this page only.
 *  4. The visitors tab shipped a debug block that the page script force-showed,
 *     dumping label counts and a JSON blob into the UI.
 */
class AdminAnalyticsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'analytics-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);
        $role->permissions()->attach(
            Permission::create([
                'name' => 'analytics.view',
                'slug' => 'analytics-view',
                'group_name' => 'analytics',
            ])->getKey()
        );

        return $admin;
    }

    /** Render the overview page, optionally with a query string. */
    protected function page(User $actor, string $query = ''): string
    {
        return $this->actingAs($actor, 'admin')
            ->get('/admin/analytics'.$query)
            ->assertOk()
            ->getContent();
    }

    /** Render one of the sibling analytics tabs, optionally with a query string. */
    protected function tab(User $actor, string $name, string $query = ''): string
    {
        return $this->actingAs($actor, 'admin')
            ->get('/admin/analytics/'.$name.$query)
            ->assertOk()
            ->getContent();
    }

    /** @return list<string> the values of every <option> marked selected */
    protected function selectedRanges(string $html): array
    {
        preg_match_all('#<option value="(\d+)"(.*?)>#s', $html, $matches, PREG_SET_ORDER);

        return array_values(array_map(
            fn (array $m): string => $m[1],
            array_filter($matches, fn (array $m): bool => str_contains($m[2], 'selected'))
        ));
    }

    public function test_the_period_picker_reflects_the_requested_range(): void
    {
        $admin = $this->admin();

        foreach ([7, 30, 90, 365] as $span) {
            $this->assertSame(
                [(string) $span],
                $this->selectedRanges($this->page($admin, '?range='.$span)),
                "range={$span} did not mark its own option"
            );
        }
    }

    public function test_the_period_picker_defaults_to_thirty_days(): void
    {
        $this->assertSame(['30'], $this->selectedRanges($this->page($this->admin())));
    }

    public function test_an_unknown_range_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/analytics?range=9999')
            ->assertSessionHasErrors('range');
    }

    public function test_the_page_renders_when_a_period_has_no_revenue(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Free Cat', 'slug' => 'free-cat', 'is_active' => true]);
        $product = Product::factory()->create();
        $category->products()->attach($product);

        // A free line item: revenue sums to exactly 0, so the old
        // `revenue / max(revenue)` bar maths divided by zero.
        $order = Order::factory()->create(['status' => 'completed', 'grand_total' => 0]);
        OrderItem::create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'product_name' => 'Free Sample',
            'sku' => 'FREE-1',
            'quantity' => 1,
            'unit_price' => 0,
            'line_total' => 0,
        ]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Free Cat', $html);
    }

    public function test_the_page_renders_with_no_data_at_all(): void
    {
        $this->page($this->admin());
    }

    public function test_money_uses_the_store_currency_not_a_hardcoded_dollar(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => 'completed', 'grand_total' => 1234.56]);

        $html = $this->page($admin);

        $this->assertStringContainsString(format_currency(1234.56), $html);
        $this->assertStringNotContainsString('$1,234.56', $html);
    }

    public function test_every_analytics_section_is_present_in_the_nav(): void
    {
        $html = $this->page($this->admin());

        foreach ([
            'admin.analytics.overview',
            'admin.analytics.products',
            'admin.analytics.visitors',
            'admin.analytics.customers',
            'admin.analytics.marketing',
            'admin.analytics.inventory',
            'admin.analytics.realtime',
            'admin.analytics.custom-report',
        ] as $routeName) {
            $this->assertStringContainsString(route($routeName), $html, "nav is missing {$routeName}");
        }
    }

    public function test_the_kpi_cards_link_to_their_detail_screens(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => 'completed', 'grand_total' => 10]);

        $html = $this->page($admin);

        $this->assertStringContainsString('href="'.route('admin.orders.index').'"', $html);
        $this->assertStringContainsString('href="'.route('admin.inventory.low-stock').'"', $html);
    }

    public function test_the_visitors_tab_no_longer_renders_debug_output(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.analytics.visitors'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="debugInfo"', $html);
        $this->assertStringNotContainsString('Debug: Chart Data', $html);
    }

    public function test_the_visitors_period_picker_reflects_the_requested_range(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.analytics.visitors', ['range' => 90]))
            ->assertOk()
            ->getContent();

        $this->assertSame(['90'], $this->selectedRanges($html));
    }

    // -----------------------------------------------------------------------
    // Per-tab guards. The remaining analytics tabs each carried a defect that
    // only surfaced once real rows existed in the database.
    // -----------------------------------------------------------------------

    protected function seedProduct(int $quantity = 5, int $reorderLevel = 0): Product
    {
        $product = Product::factory()->create(['name' => 'Seed Product', 'stock_quantity' => 12]);
        Inventory::create([
            'product_id' => $product->getKey(),
            'quantity' => $quantity,
            'reorder_level' => $reorderLevel,
        ]);

        return $product;
    }

    public function test_the_products_tab_renders_when_products_exist(): void
    {
        $this->seedProduct();

        // Product::inventory() is a HasMany, so the old `$product['inventory']
        // <= 0` compared a Collection to an int and every row was a 500.
        $this->tab($this->admin(), 'products');
    }

    public function test_the_products_stock_column_is_readable(): void
    {
        $this->seedProduct(quantity: 4);

        $html = $this->tab($this->admin(), 'products');

        $this->assertStringContainsString('4', $html);
        $this->assertStringNotContainsString('low</span>', $html, 'a healthy stock level rendered as low');
    }

    public function test_the_products_period_picker_reflects_the_requested_range(): void
    {
        $admin = $this->admin();

        foreach ([7, 90] as $span) {
            $html = $this->tab($admin, 'products', '?range='.$span);
            $this->assertSame([(string) $span], $this->selectedRanges($html));
        }
    }

    public function test_the_products_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $active = $this->seedProduct();
        $active->update(['is_active' => true, 'name' => 'Active Row']);

        $inactive = Product::factory()->create(['name' => 'Inactive Row', 'is_active' => false]);
        Inventory::create(['product_id' => $inactive->getKey(), 'quantity' => 3]);

        $all = $this->tab($admin, 'products?status=');
        $this->assertStringContainsString('Active Row', $all);
        $this->assertStringContainsString('Inactive Row', $all);

        $onlyActive = $this->tab($admin, 'products?status=active');
        $this->assertStringContainsString('Active Row', $onlyActive);
        $this->assertStringNotContainsString('Inactive Row', $onlyActive);
    }

    public function test_the_products_conversion_rate_column_is_populated(): void
    {
        $admin = $this->admin();
        $product = $this->seedProduct();
        $order = Order::factory()->create(['status' => 'completed', 'grand_total' => 30]);
        OrderItem::create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 4,
            'unit_price' => 7.5,
            'line_total' => 30,
        ]);

        $html = $this->tab($admin, 'products');

        // Nothing ever assigned conversion_rate, so the column was always blank.
        $this->assertMatchesRegularExpression('/400(\.0+)?%/', $html, 'no conversion rate rendered');
    }

    public function test_the_products_tab_uses_the_primary_image_not_the_first_one(): void
    {
        $admin = $this->admin();
        $product = $this->seedProduct();

        // Insert order matters: the analytics table used images->first(), so it
        // showed whichever row came back first rather than the primary one.
        ProductImage::create([
            'product_id' => $product->getKey(),
            'path' => 'products/secondary.jpg',
            'sort_order' => 1,
            'is_primary' => 0,
        ]);
        ProductImage::create([
            'product_id' => $product->getKey(),
            'path' => 'products/hero.jpg',
            'sort_order' => 2,
            'is_primary' => 1,
        ]);

        $html = $this->tab($admin, 'products');

        $this->assertStringContainsString('products/hero.jpg', $html);
        $this->assertStringNotContainsString('products/secondary.jpg', $html);
    }

    public function test_the_inventory_reorder_threshold_is_rendered(): void
    {
        $admin = $this->admin();

        // The threshold has to be configured for a row to be reported as low
        // stock at all. It used to be read as the empty string that
        // INVENTORY_LOW_STOCK_THRESHOLD ships as, which SQLite happily compares
        // as 0 and so reported every row, and MySQL compares as 0 and so
        // reported none: the same code, the opposite answer per dialect.
        config(['app.inventory.low_stock_threshold' => '5']);

        $this->seedProduct(quantity: 2, reorderLevel: 10);

        $html = $this->tab($admin, 'inventory');

        // The service read a `low_stock_threshold` attribute that Inventory does
        // not have, so the Threshold column rendered blank on every row.
        $this->assertStringContainsString('>10</td>', $html, 'reorder threshold not rendered');
    }

    public function test_the_realtime_tab_no_longer_relies_on_a_meta_refresh(): void
    {
        $admin = $this->admin();

        $html = $this->tab($admin, 'realtime');

        // A refresh meta inside <main> is ignored by browsers, so the page never
        // actually polled; it now has an explicit button.
        $this->assertStringNotContainsString('http-equiv="refresh"', $html);
        $this->assertStringContainsString('data-action="reloadAnalyticsPage"', $html);
    }

    public function test_every_analytics_tab_renders_against_seeded_data(): void
    {
        $admin = $this->admin();
        $this->seedProduct();

        foreach ([
            'admin.analytics.overview',
            'admin.analytics.products',
            'admin.analytics.visitors',
            'admin.analytics.customers',
            'admin.analytics.marketing',
            'admin.analytics.inventory',
            'admin.analytics.realtime',
            'admin.analytics.custom-report',
        ] as $name) {
            $this->actingAs($admin, 'admin')
                ->get(route($name))
                ->assertOk();
        }
    }

    public function test_the_customer_and_marketing_tabs_use_the_store_currency(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => 'completed', 'grand_total' => 250.00]);

        foreach (['customers', 'marketing'] as $tab) {
            $html = $this->tab($admin, $tab);
            $this->assertStringNotContainsString('$250.00', $html, "{$tab} hard-coded a dollar sign");
        }
    }
}
