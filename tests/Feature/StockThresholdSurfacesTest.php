<?php

namespace Tests\Feature;

use App\Models\FlashSale;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the three screens that read a stock threshold or a null sale date.
 *
 * Defects these screens shipped with:
 *
 *  1. flash_sales.ends_at is nullable, but the listing called
 *     `$endsAt->diffForHumans()` unguarded in its Live branch: a sale that is
 *     active with no end date raised a TypeError and took the whole page to a
 *     500.
 *  2. ReportController::inventory() compared each row against
 *     `$i->low_stock_threshold`, which is not an Inventory attribute, so
 *     `quantity <= null` was never true and the Low Stock Items table could
 *     only ever show its empty state.
 */
class StockThresholdSurfacesTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'stock-threshold-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);

        foreach (['flash_sales.view', 'reports.view', 'inventory.view'] as $permission) {
            $role->permissions()->attach(Permission::create([
                'name' => $permission,
                'slug' => str_replace('.', '-', $permission),
                'group_name' => explode('.', $permission)[0],
            ])->getKey());
        }

        return $admin;
    }

    protected function stock(string $name, int $quantity, ?int $reorderLevel = null): Inventory
    {
        static $sequence = 0;
        $sequence++;

        $product = Product::factory()->create(['name' => $name, 'sku' => 'THRESH-'.$sequence]);

        return Inventory::factory()->create([
            'product_id' => $product->getKey(),
            'quantity' => $quantity,
            'reorder_level' => $reorderLevel,
        ]);
    }

    /**
     * Just the Low Stock Items table, so a product listed in the Out of Stock
     * table below it cannot be mistaken for a low-stock row.
     */
    protected function lowStockSection(string $html): string
    {
        preg_match('/Low Stock Items<\/h2>(.*?)Out of Stock Items/s', $html, $section);

        return $section[1] ?? '';
    }

    public function test_a_live_flash_sale_without_an_end_date_still_renders(): void
    {
        FlashSale::create([
            'name' => 'Open Ended Sale',
            'sale_price' => 10,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_active' => true,
        ]);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.flash-sales.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Open Ended Sale', $html);
        $this->assertStringContainsString('No end date set', $html);
    }

    public function test_the_inventory_report_lists_rows_below_their_own_reorder_level(): void
    {
        config(['app.inventory.low_stock_threshold' => '25']);

        $this->stock('Row Own Threshold', 3, reorderLevel: 10);
        $this->stock('Row Own Threshold Healthy', 40, reorderLevel: 10);
        $this->stock('Zero Quantity Row', 0, reorderLevel: 10);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.reports.inventory'))
            ->assertOk()
            ->getContent();

        $low = $this->lowStockSection($html);

        $this->assertStringContainsString('Row Own Threshold<', $low);
        $this->assertStringNotContainsString('Row Own Threshold Healthy', $low);
        // Out of stock belongs in its own table, not the low-stock one.
        $this->assertStringNotContainsString('Zero Quantity Row', $low);
    }

    public function test_the_rows_own_reorder_level_wins_over_the_global_setting(): void
    {
        config(['app.inventory.low_stock_threshold' => '25']);

        // reorder_level is NOT NULL with a default of 0, so a row that manages its own
        // stock is judged against its own level: 120 units against a level of 100
        // is healthy, even though the global 25 would have called 8 units low.
        $this->stock('Row Above Its Own Threshold', 120, reorderLevel: 100);
        $this->stock('Row Below Its Own Threshold', 8, reorderLevel: 10);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.reports.inventory'))
            ->assertOk()
            ->getContent();

        $low = $this->lowStockSection($html);

        $this->assertStringNotContainsString('Row Above Its Own Threshold', $low);
        $this->assertStringContainsString('Row Below Its Own Threshold', $low);
    }

    public function test_the_inventory_report_says_so_when_no_threshold_is_configured(): void
    {
        config(['app.inventory.low_stock_threshold' => '']);

        $this->stock('Anything At All', 1, reorderLevel: 99);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.reports.inventory'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No low-stock threshold is configured', $html);
        $this->assertStringNotContainsString('Anything At All', $html);
    }
}
