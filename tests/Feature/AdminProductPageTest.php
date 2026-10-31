<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards for the product catalogue screen
 * (resources/views/admin/product/index.blade.php).
 *
 * Defects this page has shipped before:
 *
 *  1. `getPrimaryImageAttribute()` ignored the eager-loaded `images` relation
 *     and ran two queries per row, so a 15-row page issued 30 image queries.
 *  2. `getFilteredProducts()` accepted `stock_status` straight from the query
 *     string, so `?stock_status=anything` blanked the page with no error.
 *  3. The service supported brand, stock and sort filters, but the view only
 *     exposed search, category and status — the rest were unreachable without
 *     hand-editing the URL, and no filter value was ever echoed back as
 *     selected.
 *  4. A `sale_price` above the base price still rendered as a second red
 *     figure under the price, implying a discount that does not exist.
 */
class AdminProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'catalog-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);

        foreach (['products.view', 'products.update', 'products.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'products',
            ])->getKey());
        }

        return $admin;
    }

    protected function page(User $actor, string $query = ''): string
    {
        return $this->actingAs($actor, 'admin')
            ->get('/admin/products'.$query)
            ->assertOk()
            ->getContent();
    }

    public function test_the_page_lists_products_with_price_stock_and_status(): void
    {
        $admin = $this->admin();
        Product::factory()->create([
            'name' => 'Cast Iron Skillet',
            'is_active' => true,
            'stock_status' => 'in_stock',
            'base_price' => 49.90,
            'sale_price' => null,
        ]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Cast Iron Skillet', $html);
        $this->assertStringContainsString(format_currency(49.90), $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function test_the_page_renders_when_the_catalogue_is_empty(): void
    {
        $this->page($this->admin());
    }

    public function test_the_image_column_does_not_run_a_query_per_row(): void
    {
        $admin = $this->admin();

        foreach (range(1, 10) as $i) {
            $product = Product::factory()->create(['name' => 'Product '.$i]);
            ProductImage::create(['product_id' => $product->getKey(), 'path' => 'p'.$i.'.png', 'is_primary' => true]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->page($admin);

        $imageQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'product_images'))
            ->count();

        // One eager load for the whole page. Before the accessor honoured the
        // relation this was two queries per product.
        $this->assertSame(1, $imageQueries, 'primary_image fell back to per-row queries');
    }

    public function test_the_primary_image_is_the_one_flagged_primary(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Two Images']);

        ProductImage::create(['product_id' => $product->getKey(), 'path' => 'first.png', 'is_primary' => false]);
        ProductImage::create(['product_id' => $product->getKey(), 'path' => 'primary.png', 'is_primary' => true]);

        $html = $this->page($admin);

        $this->assertStringContainsString('storage/primary.png', $html);
    }

    public function test_an_unknown_stock_status_is_rejected_rather_than_blanking_the_page(): void
    {
        $admin = $this->admin();
        Product::factory()->create(['name' => 'Visible Product']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/products?stock_status=bogus')
            ->assertRedirect()
            ->assertSessionHasErrors('stock_status');

        $this->assertStringContainsString('Visible Product', $this->page($admin));
    }

    public function test_the_search_filter_matches_name_and_sku(): void
    {
        $admin = $this->admin();
        Product::factory()->create(['name' => 'Wool Scarf', 'sku' => 'SCARF-01']);
        Product::factory()->create(['name' => 'Leather Belt', 'sku' => 'BELT-02']);

        $byName = $this->page($admin, '?search=Scarf');
        $this->assertStringContainsString('Wool Scarf', $byName);
        $this->assertStringNotContainsString('Leather Belt', $byName);

        $bySku = $this->page($admin, '?search=BELT-02');
        $this->assertStringContainsString('Leather Belt', $bySku);
        $this->assertStringNotContainsString('Wool Scarf', $bySku);
    }

    public function test_the_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        Product::factory()->create(['name' => 'Live Item', 'is_active' => true]);
        Product::factory()->create(['name' => 'Draft Item', 'is_active' => false]);

        $active = $this->page($admin, '?status=active');
        $this->assertStringContainsString('Live Item', $active);
        $this->assertStringNotContainsString('Draft Item', $active);
    }

    public function test_the_stock_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        Product::factory()->create(['name' => 'In Stock Item', 'stock_status' => 'in_stock']);
        Product::factory()->create(['name' => 'Sold Out Item', 'stock_status' => 'out_of_stock']);

        $html = $this->page($admin, '?stock_status=out_of_stock');

        $this->assertStringContainsString('Sold Out Item', $html);
        $this->assertStringNotContainsString('In Stock Item', $html);
    }

    public function test_the_brand_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $brand = Brand::create(['name' => 'Acme', 'slug' => 'acme']);
        $branded = Product::factory()->create(['name' => 'Branded Item']);
        $branded->brands()->attach($brand);
        Product::factory()->create(['name' => 'Unbranded Item']);

        $html = $this->page($admin, '?brand='.$brand->getKey());

        $this->assertStringContainsString('Branded Item', $html);
        $this->assertStringNotContainsString('Unbranded Item', $html);
    }

    public function test_the_category_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $category = Category::create(['name' => 'Cookware', 'slug' => 'cookware', 'is_active' => true]);
        $inCategory = Product::factory()->create(['name' => 'Pan Item']);
        $inCategory->categories()->attach($category);
        Product::factory()->create(['name' => 'Other Item']);

        $html = $this->page($admin, '?category='.$category->getKey());

        $this->assertStringContainsString('Pan Item', $html);
        $this->assertStringNotContainsString('Other Item', $html);
    }

    public function test_the_sort_control_reorders_the_list(): void
    {
        $admin = $this->admin();
        Product::factory()->create(['name' => 'Alpha', 'base_price' => 50]);
        Product::factory()->create(['name' => 'Zulu', 'base_price' => 10]);

        $byName = $this->page($admin, '?sort_by=name&sort_order=asc');
        $this->assertLessThan(strpos($byName, 'Zulu'), strpos($byName, 'Alpha'));

        $byPrice = $this->page($admin, '?sort_by=base_price&sort_order=asc');
        $this->assertLessThan(strpos($byPrice, 'Alpha'), strpos($byPrice, 'Zulu'));
    }

    public function test_a_selected_filter_is_marked_in_the_control(): void
    {
        $admin = $this->admin();
        Product::factory()->create(['name' => 'Item']);

        $html = $this->page($admin, '?status=active&stock_status=out_of_stock&sort_by=name');

        $this->assertMatchesRegularExpression('/<option value="active" selected>/', $html);
        $this->assertMatchesRegularExpression('/<option value="out_of_stock" selected>/', $html);
        $this->assertMatchesRegularExpression('/<option value="name" selected>/', $html);
    }

    public function test_a_sale_price_above_the_base_price_is_not_shown_as_a_discount(): void
    {
        $admin = $this->admin();
        Product::factory()->create([
            'name' => 'Odd Pricing',
            'base_price' => 20,
            'sale_price' => 25,
        ]);

        $html = $this->page($admin);

        // base 20 with a "sale" of 25 must render one price, not a struck-through
        // base with a second red figure beneath it.
        $this->assertStringNotContainsString('line-through', $html);
    }

    public function test_a_genuine_discount_strikes_through_the_base_price(): void
    {
        $admin = $this->admin();
        Product::factory()->create([
            'name' => 'On Sale',
            'base_price' => 40,
            'sale_price' => 30,
        ]);

        $html = $this->page($admin);

        $this->assertStringContainsString('line-through', $html);
        $this->assertStringContainsString(format_currency(30.0), $html);
    }

    public function test_every_row_exposes_the_toggle_and_delete_actions(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Toggleable']);

        $html = $this->page($admin);

        $this->assertStringContainsString(route('admin.products.toggleStatus', $product->getKey()), $html);
        $this->assertStringContainsString(route('admin.products.destroy', $product->getKey()), $html);
    }

    public function test_the_images_screen_is_linked_from_each_row(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['name' => 'Has Images']);

        $this->assertStringContainsString(route('admin.products.images', $product->getKey()), $this->page($admin));
    }

    public function test_the_pagination_link_preserves_the_active_filters(): void
    {
        $admin = $this->admin();

        foreach (range(1, 20) as $i) {
            Product::factory()->create(['name' => 'Item '.$i, 'stock_status' => 'in_stock']);
        }

        $html = $this->page($admin, '?status=active&stock_status=in_stock');

        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('stock_status=in_stock', $html);
    }
}
