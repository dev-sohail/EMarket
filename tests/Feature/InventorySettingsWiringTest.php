<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The settings screen saves `low_stock_threshold` to the settings table while
 * every low-stock feature read config('app.inventory.low_stock_threshold'),
 * which comes from .env. An operator setting a threshold of 10 in Settings →
 * Order & Inventory changed nothing at all, and the dashboard told them that is
 * where to fix the warning.
 *
 * The reservation TTL had the mirror-image problem:
 * `(int) config('app.inventory.reservation_ttl_minutes', 30)` returned 0 because
 * the env var ships blank and the config default never applies, so the hourly
 * app:expire-reservations job used `now()` as its cut-off and released every
 * reservation in the shop — including carts being filled out at that moment.
 */
class InventorySettingsWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // settings.all is cached for five minutes and shared by the whole
        // process, so a row written mid-test would otherwise be invisible.
        setting_forget();
    }

    protected function saveSetting(string $key, string $value, string $group = 'inventory'): void
    {
        Setting::put($key, $value, $group);
        setting_forget();
    }

    protected function stockRow(string $name, int $quantity, int $reorderLevel = 5): Inventory
    {
        static $sequence = 0;
        $sequence++;

        $product = Product::factory()->create(['name' => $name, 'sku' => 'WIRE-'.$sequence]);

        return Inventory::factory()->create([
            'product_id' => $product->getKey(),
            'quantity' => $quantity,
            'reorder_level' => $reorderLevel,
        ]);
    }

    public function test_the_settings_table_value_wins_over_the_env_default(): void
    {
        config(['app.inventory.low_stock_threshold' => '3']);
        $this->saveSetting('low_stock_threshold', '12');

        $this->assertSame(12, low_stock_threshold());
    }

    public function test_the_env_value_is_used_when_the_settings_table_has_no_row(): void
    {
        config(['app.inventory.low_stock_threshold' => '4']);

        $this->assertSame(4, low_stock_threshold());
    }

    public function test_a_blank_settings_row_falls_through_to_the_env_value(): void
    {
        config(['app.inventory.low_stock_threshold' => '6']);
        $this->saveSetting('low_stock_threshold', '');

        $this->assertSame(6, low_stock_threshold());
    }

    public function test_an_unset_threshold_is_null_rather_than_zero(): void
    {
        config(['app.inventory.low_stock_threshold' => '']);
        $this->saveSetting('low_stock_threshold', '');

        $this->assertNull(low_stock_threshold());
    }

    public function test_the_inventory_listing_honours_a_threshold_saved_in_settings(): void
    {
        $admin = $this->admin();

        config(['app.inventory.low_stock_threshold' => '']);
        $this->saveSetting('low_stock_threshold', '10');

        $this->stockRow('Low Stock Item', 4);
        $this->stockRow('Plenty In Stock', 60);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Low Stock (1)', $html);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'inventory-wiring-admin@example.test',
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

    /** A product holding stock, plus a cart line reserving two units of it. */
    protected function reservation(): array
    {
        static $sequence = 0;
        $sequence++;

        $product = Product::factory()->create([
            'name' => 'Reserved Item '.$sequence,
            'sku' => 'RESERVE-'.$sequence,
        ]);

        $inventory = Inventory::factory()->create([
            'product_id' => $product->getKey(),
            'quantity' => 10,
            'reserved_quantity' => 2,
        ]);

        $item = CartItem::factory()->forProduct($product)->create(['quantity' => 2]);

        return [$inventory, $item];
    }

    public function test_reservation_ttl_resolves_the_same_way(): void
    {
        config(['app.inventory.reservation_ttl_minutes' => '']);
        $this->assertNull(reservation_ttl_minutes());

        config(['app.inventory.reservation_ttl_minutes' => '45']);
        $this->assertSame(45, reservation_ttl_minutes());

        $this->saveSetting('reservation_ttl_minutes', '90');
        $this->assertSame(90, reservation_ttl_minutes());
    }

    public function test_the_expiry_job_does_nothing_when_no_ttl_is_configured(): void
    {
        config(['app.inventory.reservation_ttl_minutes' => '']);

        [$inventory, $item] = $this->reservation();

        Artisan::call('app:expire-reservations');

        // A zero TTL used to delete this row and free its reserved units.
        $this->assertSame(1, CartItem::whereKey($item->getKey())->count());
        $this->assertSame(10, Inventory::whereKey($inventory->getKey())->value('quantity'));
        $this->assertSame(2, Inventory::whereKey($inventory->getKey())->value('reserved_quantity'));
    }

    public function test_the_expiry_job_releases_reservations_older_than_the_configured_ttl(): void
    {
        config(['app.inventory.reservation_ttl_minutes' => '60']);

        [$inventory, $item] = $this->reservation();

        $item->forceFill(['updated_at' => now()->subHours(3)])->save();

        Artisan::call('app:expire-reservations');

        $this->assertSame(0, CartItem::whereKey($item->getKey())->count());

        // Releasing a reservation frees the held units; the physical quantity
        // was never debited, so it stands and reserved_quantity drops.
        $this->assertSame(10, Inventory::whereKey($inventory->getKey())->value('quantity'));
        $this->assertSame(0, Inventory::whereKey($inventory->getKey())->value('reserved_quantity'));
    }

    public function test_the_expiry_job_keeps_reservations_newer_than_the_ttl(): void
    {
        config(['app.inventory.reservation_ttl_minutes' => '60']);

        [$inventory, $item] = $this->reservation();

        Artisan::call('app:expire-reservations');

        $this->assertSame(1, CartItem::whereKey($item->getKey())->count());
        $this->assertSame(10, Inventory::whereKey($inventory->getKey())->value('quantity'));
    }

    public function test_a_cart_can_remember_the_coupon_it_accepted(): void
    {
        // CheckoutController reads $cart->coupon_code on GET and clears it with
        // $cart->update(['coupon_code' => null]); both were silent no-ops while
        // carts had no such column and Cart::$fillable omitted it, so the
        // checkout page always showed a zero discount.
        $cart = Cart::create(['session_id' => 'session-abc']);

        $this->assertNull($cart->coupon_code);

        $cart->update(['coupon_code' => 'SAVE10']);

        $this->assertSame('SAVE10', $cart->fresh()->coupon_code);

        $cart->update(['coupon_code' => null]);

        $this->assertNull($cart->fresh()->coupon_code);
    }
}
