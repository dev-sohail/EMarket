<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the inventory list and the low-stock report
 * (resources/views/admin/inventory/{index,low-stock}.blade.php and
 * Admin\InventoryController::index / lowStock).
 *
 * Defects this screen shipped with:
 *
 *  1. The header carries a search box and a status/sort picker, but
 *     InventoryController::index() took no Request at all, so every control
 *     reloaded the same unfiltered, unsorted list. The form looked wired and
 *     did nothing.
 *  2. `config('app.inventory.low_stock_threshold', 5)` cannot fall back:
 *     config/app.php defaults INVENTORY_LOW_STOCK_THRESHOLD to '' and a fresh
 *     .env leaves it blank, so the key exists with an empty-string value. Bound
 *     as a parameter MySQL coerced it to 0, and the low-stock predicate
 *     (`quantity <= '' AND quantity > 0`) could never match. Every low-stock
 *     count was 0 and the low-stock report was permanently empty, silently.
 *  3. Two of the four stat cards (Out of Stock, In Stock) were rendered from
 *     variables the controller never passed, so they read 0 forever.
 *  4. The listing paginator dropped the query string, so page 2 silently threw
 *     the search and filters away.
 */
class AdminInventoryListTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'inventory-list-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);
        $role->permissions()->attach(Permission::create([
            'name' => 'inventory.view',
            'slug' => 'inventory-view',
            'group_name' => 'inventory',
        ])->getKey());

        return $admin;
    }

    protected function page(User $actor, array $query = []): string
    {
        return $this->actingAs($actor, 'admin')
            ->get(route('admin.inventory.index', $query))
            ->assertOk()
            ->getContent();
    }

    /** A product with one inventory row at an exact quantity. */
    protected function stockRow(string $name, int $quantity, ?string $sku = null): Inventory
    {
        static $sequence = 0;
        $sequence++;

        $product = Product::factory()->create([
            'name' => $name,
            'sku' => $sku ?? 'SKU-'.$sequence,
        ]);

        return Inventory::factory()->withQuantity($quantity)->create([
            'product_id' => $product->getKey(),
        ]);
    }

    public function test_search_box_actually_filters_the_listing(): void
    {
        $admin = $this->admin();
        $this->stockRow('Alpha Lamp', 40, 'ALPHA-1');
        $this->stockRow('Beta Chair', 40, 'BETA-1');

        $html = $this->page($admin, ['search' => 'Alpha']);

        $this->assertStringContainsString('Alpha Lamp', $html);
        $this->assertStringNotContainsString('Beta Chair', $html);
    }

    public function test_search_matches_the_sku_and_not_only_the_product_name(): void
    {
        $admin = $this->admin();
        $this->stockRow('Alpha Lamp', 40, 'ALPHA-1');
        $this->stockRow('Beta Chair', 40, 'BETA-9');

        $this->assertStringContainsString('Beta Chair', $this->page($admin, ['search' => 'BETA-9']));
        $this->assertStringNotContainsString('Alpha Lamp', $this->page($admin, ['search' => 'BETA-9']));
    }

    public function test_status_filter_splits_out_low_and_in_stock_rows(): void
    {
        $admin = $this->admin();
        config(['app.inventory.low_stock_threshold' => '10']);

        $this->stockRow('Out Of Stock Item', 0);
        $this->stockRow('Low Stock Item', 4);
        $this->stockRow('Plenty In Stock', 80);

        $out = $this->page($admin, ['status' => 'out']);
        $this->assertStringContainsString('Out Of Stock Item', $out);
        $this->assertStringNotContainsString('Plenty In Stock', $out);

        $low = $this->page($admin, ['status' => 'low']);
        $this->assertStringContainsString('Low Stock Item', $low);
        $this->assertStringNotContainsString('Plenty In Stock', $low);
        $this->assertStringNotContainsString('Out Of Stock Item', $low);

        $in = $this->page($admin, ['status' => 'in']);
        $this->assertStringContainsString('Plenty In Stock', $in);
        $this->assertStringNotContainsString('Low Stock Item', $in);
    }

    public function test_sort_orders_the_listing(): void
    {
        $admin = $this->admin();
        $this->stockRow('Alpha Lamp', 90);
        $this->stockRow('Zeta Sofa', 2);

        $byName = $this->page($admin, ['sort' => 'name_asc']);
        $this->assertLessThan(
            strpos($byName, 'Zeta Sofa'),
            strpos($byName, 'Alpha Lamp'),
            'name_asc should list Alpha Lamp before Zeta Sofa'
        );

        $byQuantity = $this->page($admin, ['sort' => 'qty_asc']);
        $this->assertLessThan(
            strpos($byQuantity, 'Alpha Lamp'),
            strpos($byQuantity, 'Zeta Sofa'),
            'qty_asc should list the smaller quantity first'
        );
    }

    public function test_an_unknown_filter_value_is_rejected_rather_than_silently_applied(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.inventory.index', ['status' => 'nonsense']))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.inventory.index', ['sort' => 'quantity; drop table inventories']))
            ->assertSessionHasErrors('sort');
    }

    public function test_pagination_preserves_the_active_filters(): void
    {
        $admin = $this->admin();

        foreach (range(1, 20) as $index) {
            $this->stockRow('Item '.$index, 10, 'SKU-'.$index);
        }

        config(['app.inventory.low_stock_threshold' => '5']);

        $html = $this->page($admin, ['search' => 'Item', 'status' => 'in', 'sort' => 'name_asc']);

        $this->assertStringContainsString('search=Item', $html);
        $this->assertStringContainsString('status=in', $html);
    }

    public function test_every_movement_type_the_app_writes_can_be_stored_and_filtered(): void
    {
        $admin = $this->admin();
        $inventory = $this->stockRow('Movement Host', 5)->getKey();

        // The column was created as enum('in','out','adjustment') while
        // InventoryService writes InventoryMovementType values, so under
        // STRICT_TRANS_TABLES — the production sql_mode — every stock
        // adjustment, and every order that moves stock, failed on insert.
        foreach (InventoryMovementType::cases() as $index => $type) {
            InventoryMovement::create([
                'inventory_id' => $inventory,
                'type' => $type->value,
                'quantity' => $index + 1,
                'notes' => 'Movement of type '.$type->value,
            ]);
        }

        $this->assertSame(
            count(InventoryMovementType::cases()),
            InventoryMovement::count()
        );

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.inventory.movements', ['type' => InventoryMovementType::Damage->value]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Movement of type damage', $html);
        // The badge printed "Damage" via ucfirst() on a raw column value, so
        // stock_in read as "Stock_in"; and the filter offered a "purchase" type
        // that nothing ever writes.
        $this->assertStringNotContainsString('Stock_in', $html);
        $this->assertStringNotContainsString('>Purchase<', $html);

        $filters = $this->actingAs($admin, 'admin')
            ->get(route('admin.inventory.movements'))
            ->assertOk()
            ->getContent();

        foreach (InventoryMovementType::cases() as $type) {
            $this->assertStringContainsString(
                'value="'.$type->value.'"',
                $filters,
                "the movement filter has no option for {$type->value}"
            );
        }
    }

    public function test_the_movement_history_pagination_keeps_the_date_and_type_filters(): void
    {
        $admin = $this->admin();

        // 25 matching rows is two pages at the 20-per-page default.
        foreach (range(1, 25) as $index) {
            InventoryMovement::create([
                'inventory_id' => $this->stockRow('Item '.$index, 5)->getKey(),
                'type' => InventoryMovementType::StockIn->value,
                'quantity' => 1,
                'notes' => 'Movement '.$index,
            ]);
        }

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.inventory.movements', ['type' => InventoryMovementType::StockIn->value, 'date_from' => '2000-01-01']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('type=stock_in', $html);
        $this->assertStringContainsString('date_from=2000-01-01', $html);
    }

    public function test_an_unconfigured_threshold_disables_low_stock_reporting_instead_of_matching_nothing_silently(): void
    {
        $admin = $this->admin();
        config(['app.inventory.low_stock_threshold' => '']);

        $this->stockRow('Low Stock Item', 2);

        $html = $this->page($admin);

        $this->assertStringContainsString('Low Stock (0)', $html);

        $report = $this->actingAs($admin, 'admin')
            ->get(route('admin.inventory.low-stock'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No low-stock threshold is configured', $report);
        $this->assertStringNotContainsString('Low Stock Item', $report);
    }

    public function test_a_configured_threshold_counts_and_reports_low_stock(): void
    {
        $admin = $this->admin();
        config(['app.inventory.low_stock_threshold' => '10']);

        $this->stockRow('Low Stock Item', 3);
        $this->stockRow('Fine Item', 50);

        $this->assertStringContainsString('Low Stock (1)', $this->page($admin));

        $report = $this->actingAs($admin, 'admin')
            ->get(route('admin.inventory.low-stock'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Low Stock Item', $report);
        $this->assertStringNotContainsString('Fine Item', $report);
    }

    public function test_the_stat_cards_report_real_counts(): void
    {
        $admin = $this->admin();
        config(['app.inventory.low_stock_threshold' => '10']);

        $this->stockRow('Zero', 0);
        $this->stockRow('Few', 5);
        $this->stockRow('Many', 90);

        $html = $this->page($admin);

        // One card per state: zero, at-or-below-threshold, above-threshold.
        $this->assertMatchesRegularExpression(
            '#>1</div>\s*<div class="text-xs[^"]*">Out of Stock</div>#',
            $html
        );
        $this->assertMatchesRegularExpression(
            '#>1</div>\s*<div class="text-xs[^"]*">Low Stock</div>#',
            $html
        );
        $this->assertMatchesRegularExpression(
            '#>1</div>\s*<div class="text-xs[^"]*">In Stock</div>#',
            $html
        );
    }

    public function test_the_helper_normalises_a_blank_threshold_to_null(): void
    {
        config(['app.inventory.low_stock_threshold' => '']);
        $this->assertNull(low_stock_threshold());

        config(['app.inventory.low_stock_threshold' => null]);
        $this->assertNull(low_stock_threshold());

        config(['app.inventory.low_stock_threshold' => '7']);
        $this->assertSame(7, low_stock_threshold());

        config(['app.inventory.low_stock_threshold' => '-3']);
        $this->assertSame(0, low_stock_threshold());
    }
}
