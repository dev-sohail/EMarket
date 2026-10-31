<?php

namespace Tests\Feature;

use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Api\ApiRouteRegistrar;
use App\Services\Api\ReflectedCatalogService;
use App\Services\Api\Reflection\DefinitionReflector;
use App\Services\Api\Reflection\ModelReflector;
use App\Services\Api\Reflection\ModelResolver;
use App\Services\Api\Reflection\PathConflictException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Reflection-sourced API definitions.
 *
 * The promise being tested is that a model becomes a working API from schema
 * alone: reflect it, and the definition, endpoints and parameters exist, the
 * routes are live, and filtering and detail lookup work — with no handler,
 * resource, serializer or route file written by hand.
 *
 * Routes are registered through the real ApiRouteRegistrar after each write,
 * because the module registers from the database at boot. A test that registers
 * routes by hand would pass while the real registrar never registered the
 * reflected endpoints at all.
 */
class ReflectedApiDefinitionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reflect Product and put the resulting routes in front of the router.
     *
     * Registration is skipped with `$register = false` when the test needs to
     * change the database first: the registrar only ever *adds* routes, so a
     * second call cannot unregister what the first one already put in the
     * router, and re-registering after a delete would prove nothing.
     *
     * @param  array{activate?: bool, force?: bool}  $options
     */
    protected function reflectProduct(array $options = [], bool $register = true): ApiDefinition
    {
        $definition = app(DefinitionReflector::class)->write(Product::class, $options);

        if ($register) {
            $this->registerApiRoutes();
        }

        return $definition;
    }

    protected function registerApiRoutes(): void
    {
        Route::middleware(['api'])
            ->prefix(config('api_management.prefix', 'api'))
            ->group(fn () => app(ApiRouteRegistrar::class)->register());
    }

    protected function makeProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => 'Merino Wool Sweater',
            'slug' => 'merino-wool-sweater',
            'base_price' => 129.99,
            'stock_quantity' => 10,
            'is_active' => true,
        ], $attributes));
    }

    // ---------------------------------------------------------------- blueprint

    public function test_the_blueprint_is_derived_from_the_schema(): void
    {
        $blueprint = app(ModelReflector::class)->reflect(Product::class);

        $this->assertSame('products', $blueprint->table);
        $this->assertSame('_id', $blueprint->keyName);
        $this->assertSame('products', $blueprint->path());
        $this->assertSame('products/{product}', $blueprint->detailPath());
        $this->assertSame('product', $blueprint->detailKeyName());

        // Columns become filter_<column> parameters.
        $this->assertContains('filter_name', $blueprint->filterableNames());
        $this->assertContains('filter_is_active', $blueprint->filterableNames());

        // The soft-delete column is never exposed.
        $this->assertNotContains('filter_deleted_at', $blueprint->filterableNames());
    }

    public function test_a_declared_cast_wins_over_the_raw_column_type(): void
    {
        $blueprint = app(ModelReflector::class)->reflect(Product::class);

        // `is_active` is a tinyint column, so only the model's `boolean` cast
        // distinguishes it from an integer counter.
        $this->assertSame('boolean', $blueprint->filterable['filter_is_active']['type']);
        $this->assertSame('integer', $blueprint->filterable['filter_stock_quantity']['type']);
        $this->assertSame('number', $blueprint->filterable['filter_base_price']['type']);
    }

    public function test_relations_are_discovered_from_the_model(): void
    {
        $blueprint = app(ModelReflector::class)->reflect(Product::class);

        $variants = collect($blueprint->relations)->firstWhere('property', 'variants');

        $this->assertNotNull($variants);
        $this->assertSame('hasMany', $variants['type']);
        $this->assertSame(ProductVariant::class, $variants['related_model']);
    }

    public function test_sensitive_columns_are_never_reflected(): void
    {
        $blueprint = app(ModelReflector::class)->reflect(User::class);

        $this->assertNotContains('filter_password', $blueprint->filterableNames());
    }

    public function test_a_keyless_model_is_rejected_with_an_actionable_message(): void
    {
        // ProductAttributeValue declares `$primaryKey = null`: it is a value
        // object reached through a relation, with no addressable row of its
        // own. Reflection must say so rather than failing on a null key.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has no primary key');

        app(ModelReflector::class)->reflect(ProductAttributeValue::class);
    }

    // -------------------------------------------------------------- generation

    public function test_reflecting_a_model_writes_a_usable_definition(): void
    {
        $definition = $this->reflectProduct();

        $this->assertSame('products', $definition->slug);
        $this->assertSame('reflection', $definition->source);

        // Inactive by default: generating a public URL is a decision, not a
        // side effect of running a command.
        $this->assertFalse($definition->is_active);

        $paths = $definition->endpoints->pluck('path')->all();

        $this->assertSame(['products', 'products/{product}'], $paths);

        foreach ($definition->endpoints as $endpoint) {
            $this->assertSame('service', $endpoint->handler_type);
            $this->assertStringStartsWith(
                ReflectedCatalogService::class.'::',
                $endpoint->action
            );
            $this->assertFalse($endpoint->requires_auth);
        }

        $this->assertSame(
            1,
            $definition->endpoints->firstWhere('path', 'products/{product}')->parameters->count()
        );
    }

    public function test_the_sort_parameter_is_allow_listed_to_real_columns(): void
    {
        $definition = $this->reflectProduct();

        $sort = $definition->endpoints->firstWhere('path', 'products')->parameters
            ->firstWhere('name', 'sort');

        $this->assertStringContainsString('in:name,', implode(',', $sort->validationRules()));
        $this->assertStringNotContainsString('deleted_at', implode(',', $sort->validationRules()));
    }

    public function test_reflection_is_idempotent(): void
    {
        $first = $this->reflectProduct();
        $second = $this->reflectProduct();

        $this->assertSame($first->_id, $second->_id);
        $this->assertSame(1, ApiDefinition::count());
        $this->assertSame(2, ApiEndpoint::count());

        // Parameters are replaced wholesale, never duplicated.
        $index = $second->endpoints->firstWhere('path', 'products');
        $this->assertSame(
            $index->parameters->count(),
            $index->parameters->pluck('name')->unique()->count()
        );
    }

    public function test_regenerating_does_not_deactivate_a_published_definition(): void
    {
        $this->reflectProduct(['activate' => true]);

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();
        $this->assertTrue($definition->is_active);

        // Re-running after a migration is the normal workflow, and must not
        // take a live API offline.
        $this->reflectProduct();

        $this->assertTrue($definition->fresh()->is_active);
    }

    public function test_regenerating_revives_a_soft_deleted_definition(): void
    {
        $this->reflectProduct();

        $definition = ApiDefinition::where('slug', 'products')->firstOrFail();
        $definition->delete();

        $this->assertSame(0, ApiDefinition::count());

        $this->reflectProduct();

        $this->assertSame(1, ApiDefinition::count());
        $this->assertSame(2, ApiEndpoint::count());
    }

    public function test_the_model_is_resolved_back_from_its_table(): void
    {
        $resolver = app(ModelResolver::class);
        $resolver->flush();

        $this->assertSame(Product::class, $resolver->forTable('products'));
        $this->assertNull($resolver->forTable('nothing_here'));
    }

    // ---------------------------------------------------------------- conflicts

    public function test_reflection_refuses_to_shadow_another_definitions_url(): void
    {
        $other = ApiDefinition::create([
            'name' => 'Legacy', 'slug' => 'legacy', 'source' => 'manual', 'is_active' => true,
        ]);

        // Same URL shape, different placeholder name — which the router treats
        // as the identical route.
        ApiEndpoint::create([
            'api_definition_id' => $other->getKey(),
            'method' => 'GET',
            'path' => 'products/{id}',
            'action' => 'SomeService::show',
            'handler_type' => 'service',
            'requires_auth' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        try {
            app(DefinitionReflector::class)->write(Product::class);
            $this->fail('Expected a PathConflictException.');
        } catch (PathConflictException $e) {
            $this->assertSame('products/{product}', $e->conflicts()[0]['path']);
            $this->assertSame('Legacy', $e->conflicts()[0]['conflicts'][0]['definition']);
        }

        // Nothing was written.
        $this->assertSame(0, ApiEndpoint::where('path', 'products')->count());
    }

    public function test_a_conflict_with_a_retired_definition_is_not_a_conflict(): void
    {
        $other = ApiDefinition::create([
            'name' => 'Legacy', 'slug' => 'legacy', 'source' => 'manual', 'is_active' => true,
        ]);

        ApiEndpoint::create([
            'api_definition_id' => $other->getKey(),
            'method' => 'GET',
            'path' => 'products/{id}',
            'action' => 'SomeService::show',
            'handler_type' => 'service',
            'requires_auth' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Retiring the definition takes its endpoints out of the route table,
        // so they can no longer shadow anything.
        $other->delete();

        $definition = app(DefinitionReflector::class)->write(Product::class);

        $this->assertSame('products', $definition->slug);
    }

    public function test_endpoints_of_a_soft_deleted_definition_are_not_routed(): void
    {
        $definition = $this->reflectProduct(['activate' => true], register: false);
        $this->assertTrue($definition->endpoints->every(fn ($e) => $e->is_active));

        // Deleting a definition does not touch its endpoint rows, so the
        // endpoints are still `is_active` — yet they must stop being routed.
        $definition->delete();

        $this->assertSame(2, ApiEndpoint::where('is_active', true)->count());

        $this->registerApiRoutes();

        $this->getJson('/api/products')->assertNotFound();
    }

    // ----------------------------------------------------------------- runtime

    public function test_the_reflected_listing_is_public_and_paginated(): void
    {
        $this->reflectProduct(['activate' => true]);

        $this->makeProduct(['name' => 'Wool Sweater']);
        $this->makeProduct(['name' => 'Cotton Shirt', 'slug' => 'cotton-shirt']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.table', 'products')
            ->assertJsonPath('data.pagination.total', 2);

        $this->getJson('/api/products?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_reflected_records_can_be_filtered_by_column(): void
    {
        $this->reflectProduct(['activate' => true]);

        $this->makeProduct(['name' => 'Wool Sweater']);
        $this->makeProduct(['name' => 'Cotton Shirt', 'slug' => 'cotton-shirt', 'is_active' => false]);

        $this->getJson('/api/products?filter_is_active=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Wool Sweater');

        $this->getJson('/api/products?filter_is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Cotton Shirt');
    }

    public function test_a_string_filter_supports_exact_substring_and_prefix(): void
    {
        $this->reflectProduct(['activate' => true]);

        $this->makeProduct(['name' => 'Merino Wool Sweater']);
        $this->makeProduct(['name' => 'Cotton Shirt', 'slug' => 'cotton-shirt']);

        // Exact.
        $this->getJson('/api/products?filter_name=Cotton%20Shirt')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Cotton Shirt');

        // Substring — the `~` is part of the value, not the parameter name.
        $this->getJson('/api/products?filter_name=wool~')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Merino Wool Sweater');

        // Prefix.
        $this->getJson('/api/products?filter_name=Cotton^')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Cotton Shirt');
    }

    public function test_a_numeric_filter_is_a_threshold(): void
    {
        $this->reflectProduct(['activate' => true]);

        $this->makeProduct(['name' => 'Cheap', 'base_price' => 20]);
        $this->makeProduct(['name' => 'Pricey', 'base_price' => 900, 'slug' => 'pricey']);

        $this->getJson('/api/products?filter_base_price=100')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Pricey');
    }

    public function test_the_reflected_listing_can_be_sorted(): void
    {
        $this->reflectProduct(['activate' => true]);

        $this->makeProduct(['name' => 'Beta']);
        $this->makeProduct(['name' => 'Alpha', 'slug' => 'alpha']);

        $this->getJson('/api/products?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Alpha')
            ->assertJsonPath('data.items.1.name', 'Beta');
    }

    public function test_an_unknown_sort_column_is_rejected_rather_than_ignored(): void
    {
        $this->reflectProduct(['activate' => true]);

        $this->makeProduct();

        // Allow-listed at the validation layer, so a caller cannot order by or
        // leak anything outside the reflected column set.
        $this->getJson('/api/products?sort=deleted_at')
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    public function test_the_reflected_detail_route_resolves_by_slug_and_by_key(): void
    {
        $this->reflectProduct(['activate' => true]);

        $product = $this->makeProduct();

        $this->getJson('/api/products/merino-wool-sweater')
            ->assertOk()
            ->assertJsonPath('data.record.name', 'Merino Wool Sweater')
            ->assertJsonPath('data.table', 'products');

        $this->getJson('/api/products/'.$product->getKey())
            ->assertOk()
            ->assertJsonPath('data.record.name', 'Merino Wool Sweater');
    }

    public function test_the_reflected_detail_route_answers_404_for_a_missing_record(): void
    {
        $this->reflectProduct(['activate' => true]);

        $this->getJson('/api/products/nothing-here')
            ->assertNotFound()
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_an_inactive_definition_is_not_reachable(): void
    {
        // Reflected inactive, which is the default.
        $this->reflectProduct();

        $this->getJson('/api/products')->assertNotFound();
    }

    public function test_a_new_column_becomes_a_filter_after_regenerating(): void
    {
        $definition = $this->reflectProduct(['activate' => true]);

        $names = $definition->endpoints->firstWhere('path', 'products')
            ->parameters->pluck('name');

        // Every filterable column got a parameter, so a migration that adds a
        // column turns into a usable filter on the next run.
        $this->assertSame(
            app(ModelReflector::class)->reflect(Product::class)->filterableNames(),
            $names->filter(fn ($name) => str_starts_with($name, 'filter_'))->values()->all()
        );
    }
}
