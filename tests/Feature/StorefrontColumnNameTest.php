<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiscountCampaign;
use App\Models\FlashSale;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Storefront and customer-facing screens that named a column which does not
 * exist. No table in this schema has an `id` column — every primary key is the
 * string `_id` — and several screens spelled a real column differently from the
 * table. MySQL rejects the first kind outright and returns null for the second,
 * so these pages 500'd or silently lost content while the SQLite suite stayed
 * green.
 *
 *  - store/category/show.blade.php filtered brands with
 *    `where('categories.id', …)`: a 500 on every category page.
 *  - store/product/show.blade.php prices a product from
 *    $activeFlashSale->pivot / $activeCampaign->pivot, but ShopController
 *    obtained both through a relation query, which carries no pivot, so every
 *    promotion quietly fell back to its sale-wide price.
 *  - admin/customer/show.blade.php read address_line1 where the column is
 *    address_line_1; customer/order/show.blade.php read zip_code where it is
 *    postal_code.
 */
class StorefrontColumnNameTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(string $permission): User
    {
        $admin = User::factory()->create([
            'email' => 'columns-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);
        $role->permissions()->attach(Permission::create([
            'name' => $permission,
            'slug' => str_replace('.', '-', $permission),
            'group_name' => explode('.', $permission)[0],
        ])->getKey());

        return $admin;
    }

    public function test_the_category_page_lists_the_brands_of_its_products(): void
    {
        $category = Category::create(['name' => 'Knitwear', 'slug' => 'knitwear', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Northwind', 'slug' => 'northwind', 'is_active' => true]);

        $product = Product::factory()->create([
            'name' => 'Wool Scarf',
            'is_active' => true,
            'stock_quantity' => 5,
        ]);
        $product->categories()->attach($category->getKey());
        $product->brands()->attach($brand->getKey());

        $html = $this->get('/category/knitwear')->assertOk()->getContent();

        // The sidebar filter ran `where('categories.id', …)`, which no table
        // supports: MySQL answered "Unknown column", so the page never rendered
        // at all.
        $this->assertStringContainsString('Wool Scarf', $html);
        $this->assertMatchesRegularExpression('/Northwind/', $html);
    }

    public function test_a_flash_sale_prices_a_product_by_its_own_pivot_row(): void
    {
        $product = Product::factory()->create([
            'name' => 'Wool Scarf',
            'slug' => 'wool-scarf',
            'is_active' => true,
            'base_price' => 100,
            'stock_quantity' => 5,
        ]);

        $sale = FlashSale::create([
            'name' => 'Winter Sale',
            'sale_price' => 55,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $product->flashSales()->attach($sale->getKey(), ['flash_price' => 77, 'quantity' => 10, 'sold' => 0]);

        $html = $this->get('/product/wool-scarf')->assertOk()->getContent();

        // 77 is the per-product pivot price; 55 is the sale-wide price the page
        // used to show because the pivot was missing.
        $this->assertMatchesRegularExpression('/77(\.00)?/', $html);
    }

    public function test_a_campaign_prices_a_product_by_its_own_pivot_row(): void
    {
        $product = Product::factory()->create([
            'name' => 'Wool Scarf',
            'slug' => 'wool-scarf-campaign',
            'is_active' => true,
            'base_price' => 100,
            'stock_quantity' => 5,
        ]);

        $campaign = DiscountCampaign::create([
            'name' => 'Winter Campaign',
            'type' => 'fixed',
            'value' => 20,
            'minimum_order' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
        $product->campaigns()->attach($campaign->getKey(), ['campaign_price' => 63]);

        $html = $this->get('/product/wool-scarf-campaign')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/63(\.00)?/', $html);
    }

    public function test_the_admin_customer_page_shows_the_street_address(): void
    {
        $admin = $this->admin('customers.view');
        $customer = Customer::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $customer->addresses()->create([
            'type' => 'shipping',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '07000000000',
            'address_line_1' => '12 Analytical Way',
            'city' => 'London',
            'state' => 'Greater London',
            'postal_code' => 'EC1A 1BB',
            'country' => 'GB',
            'is_default' => true,
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('12 Analytical Way', $html);
    }
}
