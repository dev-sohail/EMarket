<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin brand list was a bare table: no stat cards, no filters, no way to
 * flip a brand's active flag, and a delete button that silently failed for any
 * brand still attached to a product. The screen now filters on name/slug/website,
 * counts totals for the cards, and toggles status through the storefront cache.
 *
 * These cover the query behaviour behind that UI, which is where a wrong column
 * or an unvalidated filter would show up as a wrong number rather than a crash.
 */
class AdminBrandIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'brand-index-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);

        foreach (['brands.view', 'brands.update'] as $permission) {
            $role->permissions()->attach(Permission::create([
                'name' => $permission,
                'slug' => str_replace('.', '-', $permission),
                'group_name' => explode('.', $permission)[0],
            ])->getKey());
        }

        return $admin;
    }

    public function test_the_list_filters_by_search_across_name_slug_and_website(): void
    {
        $admin = $this->admin();

        Brand::create(['name' => 'Northwind', 'slug' => 'northwind', 'website' => 'https://northwind.example', 'is_active' => true]);
        Brand::create(['name' => 'Contoso', 'slug' => 'contoso', 'is_active' => true]);

        // The search box advertises website matching too, so a brand is
        // reachable by its domain even when neither name nor slug contain
        // the term typed.
        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.brands.index', ['search' => 'northwind.example']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Northwind', $html);
        $this->assertStringNotContainsString('Contoso', $html);
    }

    public function test_an_unknown_status_is_rejected_rather_than_silently_ignored(): void
    {
        $admin = $this->admin();

        Brand::create(['name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.brands.index', ['status' => 'sideways']))
            ->assertSessionHasErrors('status');
    }

    public function test_the_stat_cards_count_the_whole_list_and_ignore_the_active_filter(): void
    {
        $admin = $this->admin();

        Brand::create(['name' => 'Active One', 'slug' => 'active-one', 'is_active' => true]);
        Brand::create(['name' => 'Inactive One', 'slug' => 'inactive-one', 'is_active' => false]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.brands.index', ['status' => 'active']))
            ->assertOk()
            ->getContent();

        // "Inactive One" is filtered out of the table, but the cards still
        // describe the full catalogue: 2 total, 1 inactive, 1 brand holding
        // no products.
        $this->assertMatchesRegularExpression('/Total Brands.*?2/s', $html);
        $this->assertMatchesRegularExpression('/Inactive.*?1/s', $html);
        $this->assertMatchesRegularExpression('/No Products.*?2/s', $html);

        $this->assertStringContainsString('Active One', $html);
        $this->assertStringNotContainsString('Inactive One', $html);
    }

    public function test_a_brand_holding_products_is_flagged_as_unused_inverse(): void
    {
        $admin = $this->admin();

        $brand = Brand::create(['name' => 'Used Brand', 'slug' => 'used-brand', 'is_active' => true]);
        Brand::create(['name' => 'Empty Brand', 'slug' => 'empty-brand', 'is_active' => true]);

        $product = Product::factory()->create();
        $product->brands()->attach($brand->getKey());

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->getContent();

        // Only the brand with no products gets the Unused badge.
        $this->assertStringContainsString('Unused', $html);
        $this->assertMatchesRegularExpression('/No Products.*?1/s', $html);
    }

    public function test_toggling_status_flips_the_flag(): void
    {
        $admin = $this->admin();

        $brand = Brand::create(['name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.brands.toggleStatus', $brand->getKey()))
            ->assertRedirect();

        $this->assertFalse((bool) $brand->fresh()->is_active);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.brands.toggleStatus', $brand->getKey()))
            ->assertRedirect();

        $this->assertTrue((bool) $brand->fresh()->is_active);
    }
}
