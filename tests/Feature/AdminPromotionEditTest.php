<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\DiscountCampaign;
use App\Models\FlashSale;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The flash-sale and campaign edit screens built their selected-product and
 * selected-category lists with `Collection::pluck('getKey')`. pluck() reads an
 * *attribute*, not a method, so on an Eloquent collection it silently yielded
 * nulls and every checkbox rendered unchecked. Both controllers then `sync()`
 * exactly what the form posted — so renaming a flash sale, or editing a
 * campaign's dates, detached every product and category from it and the
 * promotion quietly vanished from the storefront.
 *
 * The role edit screen had the identical defect and is already fixed there;
 * these five call sites were missed.
 */
class AdminPromotionEditTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(string ...$permissions): User
    {
        $admin = User::factory()->create([
            'email' => 'promotion-edit-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);

        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create([
                'name' => $permission,
                'slug' => str_replace('.', '-', $permission),
                'group_name' => explode('.', $permission)[0],
            ])->getKey());
        }

        return $admin;
    }

    public function test_the_flash_sale_edit_screen_preserves_its_product_and_category_checkboxes(): void
    {
        $admin = $this->admin('flash_sales.view', 'flash_sales.update');

        $product = Product::factory()->create(['name' => 'Wool Scarf']);
        $sale = FlashSale::create([
            'name' => 'Winter Sale',
            'sale_price' => 40,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(3),
            'is_active' => true,
        ]);
        $sale->products()->attach($product->getKey(), ['flash_price' => 30, 'quantity' => 10, 'sold' => 0]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.flash-sales.edit', $sale))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="product_ids\[\]" value="'.preg_quote($product->getKey(), '/').'"\s+checked/',
            $html,
            'the product the sale already holds is not ticked'
        );

        // Renaming the sale must not detach what it already sells.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.flash-sales.update', $sale), [
                'name' => 'Winter Sale Renamed',
                'sale_price' => 40,
                'starts_at' => now()->subDay()->toDateTimeString(),
                'ends_at' => now()->addDays(3)->toDateTimeString(),
                'is_active' => '1',
                'product_ids' => [$product->getKey()],
                'category_ids' => [],
            ])
            ->assertRedirect();

        $this->assertSame(1, $sale->products()->count());
        $this->assertSame('Winter Sale Renamed', $sale->fresh()->name);
    }

    public function test_the_campaign_edit_screen_preserves_its_product_checkboxes(): void
    {
        $admin = $this->admin('campaigns.view', 'campaigns.update');

        $product = Product::factory()->create(['name' => 'Wool Scarf']);
        $campaign = DiscountCampaign::create([
            'name' => 'Winter Campaign',
            'type' => 'percentage',
            'value' => 10,
            'minimum_order' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(3),
            'is_active' => true,
        ]);
        $campaign->products()->attach($product->getKey(), ['campaign_price' => 80]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.campaigns.edit', $campaign))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="product_ids\[\]" value="'.preg_quote($product->getKey(), '/').'"\s+checked/',
            $html,
            'the product the campaign already targets is not ticked'
        );
    }

    public function test_the_brand_website_field_survives_a_save(): void
    {
        $admin = $this->admin('brands.view', 'brands.create', 'brands.update');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.brands.store'), [
                'name' => 'Northwind',
                'slug' => 'northwind',
                'website' => 'https://northwind.example',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $brand = Brand::where('slug', 'northwind')->firstOrFail();

        // brands had no `website` column and Brand::$fillable never listed it,
        // so a validated URL was discarded without a word.
        $this->assertSame('https://northwind.example', $brand->website);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.brands.edit', $brand))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="https://northwind.example"', $html);
    }
}
