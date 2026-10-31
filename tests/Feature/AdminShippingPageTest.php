<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the admin shipping overview (resources/views/admin/shipping/index.blade.php).
 *
 * The page reads counts off withCount() aggregates and the zone/method rule price
 * range off the eager-loaded rules, so it has to render both with and without rows.
 */
class AdminShippingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'shipping-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        foreach (['shipping.view', 'shipping.create', 'shipping.update', 'shipping.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'shipping',
            ])->getKey());
        }

        return $admin;
    }

    protected function page(User $admin): string
    {
        return $this->actingAs($admin, 'admin')
            ->get('/admin/shipping')
            ->assertOk()
            ->getContent();
    }

    public function test_overview_renders_with_zones_methods_and_rules(): void
    {
        $admin = $this->admin();

        $zone = ShippingZone::create(['name' => 'Switzerland', 'is_active' => true]);
        $zone->cities()->createMany([['city' => 'Zurich'], ['city' => 'Bern']]);

        $express = ShippingMethod::create([
            'name' => 'Express',
            'base_cost' => 25.50,
            'per_kg_cost' => 1.25,
            'estimated_days' => 1,
            'is_active' => true,
        ]);

        ShippingRule::create([
            'shipping_zone_id' => $zone->getKey(),
            'shipping_method_id' => $express->getKey(),
            'price' => 9.90,
            'min_weight' => 0,
            'is_active' => true,
        ]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Switzerland', $html);
        $this->assertStringContainsString('Express', $html);
        $this->assertStringContainsString('2 cities', $html);
        // Base cost and the per-kg surcharge are both surfaced.
        $this->assertStringContainsString('25.50', $html);
        $this->assertStringContainsString('/kg', $html);
    }

    public function test_overview_renders_with_no_shipping_data(): void
    {
        $html = $this->page($this->admin());

        $this->assertStringContainsString('No zones yet', $html);
        $this->assertStringContainsString('No methods yet', $html);
    }

    public function test_zone_rule_price_range_is_ordered_by_value(): void
    {
        $admin = $this->admin();

        $zone = ShippingZone::create(['name' => 'Alps', 'is_active' => true]);
        $standard = ShippingMethod::create(['name' => 'Standard', 'base_cost' => 5, 'is_active' => true]);

        // price is a decimal:2 cast, so the range must still read low-to-high.
        foreach ([10.00, 9.90] as $price) {
            ShippingRule::create([
                'shipping_zone_id' => $zone->getKey(),
                'shipping_method_id' => $standard->getKey(),
                'price' => $price,
                'is_active' => true,
            ]);
        }

        $html = $this->page($admin);

        $this->assertMatchesRegularExpression('/9\.90.*10\.00/', $html);
    }

    public function test_inactive_zone_and_method_render_without_error(): void
    {
        $admin = $this->admin();

        ShippingZone::create(['name' => 'Retired Zone', 'is_active' => false]);
        ShippingMethod::create([
            'name' => 'Retired Method',
            'base_cost' => 0,
            'per_kg_cost' => null,
            'estimated_days' => null,
            'is_active' => false,
        ]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Retired Zone', $html);
        $this->assertStringContainsString('Retired Method', $html);
        $this->assertStringContainsString('Inactive', $html);
    }
}
