<?php

namespace Tests\Feature;

use App\Models\ApiEndpoint;
use App\Models\Category;
use App\Models\Product;
use App\Services\Api\ApiRouteRegistrar;
use Database\Seeders\ApiManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The storefront definition is the public, unauthenticated half of the dynamic
 * API: catalogue listing, filtering, facets and product detail.
 *
 * The definitions are created by the real seeder rather than hand-built rows, so
 * these tests also prove the seeder registers working endpoints — a drift
 * between the seeder and the dispatcher's contract would fail here rather than
 * in production.
 */
class StoreCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApiManagementSeeder::class);

        // Endpoints are registered at boot, which happened before the
        // migrations ran. Re-run the registrar so the seeded rows become
        // routable, exactly as they would be after `route:clear`.
        Route::middleware(['api'])->prefix(config('api_management.prefix', 'api'))
            ->group(fn () => app(ApiRouteRegistrar::class)->register());
    }

    public function test_the_public_catalogue_lists_published_products(): void
    {
        Product::factory()->create(['name' => 'Wool Scarf', 'base_price' => 45]);
        Product::factory()->create(['name' => 'Leather Belt', 'base_price' => 80]);

        $this->getJson('/api/store/products')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Wool Scarf')
            // Cost price is hidden by the model and must not reappear here.
            ->assertJsonMissingPath('data.items.0.cost_price');
    }

    public function test_draft_and_future_products_are_absent_from_the_public_catalogue(): void
    {
        Product::factory()->create(['name' => 'Visible Tee', 'is_active' => true]);
        Product::factory()->inactive()->create(['name' => 'Draft Hoodie']);
        Product::factory()->create([
            'name' => 'Next Season Jacket',
            'published_at' => now()->addWeek(),
        ]);

        $this->getJson('/api/store/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Visible Tee');
    }

    public function test_the_listing_can_be_searched(): void
    {
        Product::factory()->create(['name' => 'Merino Socks']);
        Product::factory()->create(['name' => 'Canvas Tote']);

        $this->getJson('/api/store/products?search=Merino')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Merino Socks');
    }

    public function test_the_listing_can_be_filtered_by_price(): void
    {
        Product::factory()->create(['name' => 'Cheap', 'base_price' => 20]);
        Product::factory()->create(['name' => 'Mid', 'base_price' => 250]);
        Product::factory()->create(['name' => 'Pricey', 'base_price' => 900]);

        $this->getJson('/api/store/products?min_price=100&max_price=500')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Mid');
    }

    public function test_the_listing_can_be_filtered_by_category(): void
    {
        $category = Category::create(['name' => 'Knitwear', 'slug' => 'knitwear', 'is_active' => true]);
        $other = Category::create(['name' => 'Footwear', 'slug' => 'footwear', 'is_active' => true]);

        $knit = Product::factory()->create(['name' => 'Wool Jumper']);
        $knit->categories()->attach($category->getKey());

        Product::factory()->create(['name' => 'Trainers'])->categories()->attach($other->getKey());

        $this->getJson('/api/store/products?category=knitwear')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Wool Jumper');
    }

    public function test_the_listing_is_paginated(): void
    {
        Product::factory()->count(5)->create();

        $this->getJson('/api/store/products?per_page=2&page=1')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonPath('data.pagination.current_page', 1);

        $this->getJson('/api/store/products?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_an_unknown_sort_key_falls_back_instead_of_reaching_the_database(): void
    {
        Product::factory()->count(2)->create();

        // `sort` is allow-listed in the seeder's rules, so this is rejected at
        // validation rather than silently trusted.
        $this->getJson('/api/store/products?sort=id;DROP TABLE products')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');

        $this->assertDatabaseCount('products', 2);
    }

    public function test_a_filter_endpoint_exposes_the_facets(): void
    {
        $category = Category::create(['name' => 'Knitwear', 'slug' => 'knitwear', 'is_active' => true]);

        Product::factory()->create(['base_price' => 50])->categories()->attach($category->getKey());
        Product::factory()->create(['base_price' => 150]);

        $this->getJson('/api/store/filters')
            ->assertOk()
            ->assertJsonPath('data.price.min', 50)
            ->assertJsonPath('data.price.max', 150)
            ->assertJsonPath('data.categories.0.slug', 'knitwear')
            ->assertJsonPath('data.categories.0.product_count', 1)
            ->assertJsonStructure(['data' => ['sorts', 'availability']]);
    }

    public function test_product_detail_is_served_by_slug(): void
    {
        $product = Product::factory()->create([
            'name' => 'Wool Scarf',
            'slug' => 'wool-scarf',
            'description' => 'A very warm scarf.',
        ]);

        $this->getJson('/api/store/products/wool-scarf')
            ->assertOk()
            ->assertJsonPath('data.id', $product->getKey())
            ->assertJsonPath('data.name', 'Wool Scarf')
            ->assertJsonPath('data.description', 'A very warm scarf.')
            ->assertJsonStructure(['data' => ['images', 'variants', 'categories', 'brands']]);
    }

    public function test_product_detail_is_also_served_by_id(): void
    {
        $product = Product::factory()->create(['slug' => 'wool-scarf']);

        $this->getJson('/api/store/products/'.$product->getKey())
            ->assertOk()
            ->assertJsonPath('data.slug', 'wool-scarf');
    }

    public function test_an_unknown_product_is_a_404_not_a_server_error(): void
    {
        // A handler throwing NotFoundHttpException must keep its own status:
        // ApiDynamicController used to relabel every Throwable as a 500.
        $this->getJson('/api/store/products/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_a_draft_product_is_a_404_on_detail(): void
    {
        Product::factory()->inactive()->create(['slug' => 'secret-hoodie']);

        $this->getJson('/api/store/products/secret-hoodie')->assertStatus(404);
    }

    public function test_the_storefront_definition_needs_no_authentication(): void
    {
        $endpoint = ApiEndpoint::where('path', 'store/products')->firstOrFail();

        $this->assertFalse($endpoint->requires_auth);
        $this->assertCount(0, $endpoint->permissions);
    }

    /**
     * An explicit eager-load column list must name the related model's own
     * primary key.
     *
     * This exists because the SQLite test database cannot catch getting it
     * wrong. `ProductImage` is a prefixed model keyed on `_id`, and the listing
     * eager-loads it with an explicit column list. Naming `id` instead of `_id`
     * 500s the live MySQL storefront with "Unknown column 'id'", while SQLite
     * resolves the unknown double-quoted identifier to the *string literal*
     * `'id'` and returns happily — so a green suite said nothing either way.
     *
     * Asserting on the generated SQL is the only check that holds on both
     * drivers, so it is the right level to pin the rule at.
     */
    public function test_the_image_eager_load_selects_the_prefixed_primary_key(): void
    {
        Product::factory()->create();

        DB::enableQueryLog();

        $this->getJson('/api/store/products')->assertOk();

        $imageQuery = collect(DB::getQueryLog())
            ->pluck('query')
            ->first(fn (string $query) => str_contains($query, 'product_images')
                && str_contains($query, 'where'));

        $this->assertNotNull($imageQuery, 'The listing did not eager-load product_images.');

        // The related key has to be present...
        $this->assertMatchesRegularExpression(
            '/select\s+.*\b_id\b/i',
            $imageQuery,
            'The image eager load does not select the `_id` primary key, so MySQL would reject it.'
        );

        // ...and it must not be selecting a bare `id`, which is the exact
        // mistake this guards against.
        $this->assertDoesNotMatchRegularExpression(
            '/select\s+`?id`?\s*[,`]/i',
            $imageQuery,
            'The image eager load still selects a bare `id` column.'
        );
    }
}
