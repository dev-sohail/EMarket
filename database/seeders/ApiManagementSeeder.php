<?php

namespace Database\Seeders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\ApiEndpointPermission;
use App\Models\Permission;
use App\Services\Api\AdminOrdersService;
use App\Services\Api\StoreCatalogService;
use Illuminate\Database\Seeder;

/**
 * Registers the two shipped API definitions: the public storefront catalogue
 * and the authenticated admin order book.
 *
 * Idempotent by design — every row is matched on its natural key and updated
 * in place — so this can be re-run after a deploy to pick up handler or
 * parameter changes without duplicating endpoints.
 *
 * Endpoints use `handler_type = service`, which is what ApiEndpoint::resolveHandler
 * and ApiDynamicController actually dispatch on (`Class::method`). The `controller`,
 * `query_builder` and `action` values the admin form once offered are not wired to
 * a dispatcher and would 500 at request time.
 */
class ApiManagementSeeder extends Seeder
{
    public function run(): void
    {
        $storefront = $this->upsertDefinition(
            name: 'Storefront Data',
            slug: 'storefront-data',
            description: 'Public, read-only access to the product catalogue: listing, '
                .'filtering, facets and full product detail for the storefront.',
        );

        $this->registerStorefrontEndpoints($storefront);

        $admin = $this->upsertDefinition(
            name: 'Admin Orders',
            slug: 'admin-orders',
            description: 'Authenticated order administration. Requires a bearer token '
                .'belonging to a user holding the orders.view permission.',
        );

        $this->registerAdminEndpoints($admin);
    }

    /**
     * Create or refresh a definition by slug, reviving it if it was deleted.
     *
     * Deliberately not `updateOrCreate`: the global SoftDeletes scope hides a
     * deleted row, so Eloquent would try to INSERT a new one and die on the
     * `slug` unique index. Deleting a definition in the admin UI and re-running
     * this seeder has to restore it, not crash.
     */
    protected function upsertDefinition(string $name, string $slug, string $description): ApiDefinition
    {
        $attributes = [
            'name' => $name,
            'description' => $description,
            'source' => 'manual',
            'is_active' => true,
        ];

        $existing = ApiDefinition::withTrashed()->where('slug', $slug)->first();

        if ($existing !== null) {
            $existing->restore();
            $existing->fill($attributes)->save();

            return $existing;
        }

        return ApiDefinition::create($attributes + ['slug' => $slug]);
    }

    /**
     * Storefront endpoints: public, so `requires_auth` is false and no
     * permission grants are attached — the dispatcher's own rule is that a
     * public endpoint with no grants is open to everyone.
     *
     * `requires_auth` is stated explicitly rather than left to the column
     * default, which is TRUE: omitting it here silently shipped a public
     * catalogue behind the API auth middleware.
     */
    protected function registerStorefrontEndpoints(ApiDefinition $definition): void
    {
        $products = $this->upsertEndpoint($definition, [
            'method' => 'GET',
            'path' => 'store/products',
            'action' => StoreCatalogService::class.'::products',
            'handler_type' => 'service',
            'description' => 'Paginated product listing with search, taxonomy, price, stock and promotion filters.',
            'requires_auth' => false,
            'sort_order' => 10,
        ]);

        $this->syncParameters($products, [
            ['name' => 'search', 'location' => 'query', 'type' => 'string', 'rules' => ['string', 'max:120']],
            ['name' => 'category', 'location' => 'query', 'type' => 'string', 'rules' => ['string', 'max:255']],
            ['name' => 'brand', 'location' => 'query', 'type' => 'string', 'rules' => ['string', 'max:255']],
            ['name' => 'min_price', 'location' => 'query', 'type' => 'number', 'rules' => ['numeric', 'min:0']],
            ['name' => 'max_price', 'location' => 'query', 'type' => 'number', 'rules' => ['numeric', 'min:0']],
            ['name' => 'in_stock', 'location' => 'query', 'type' => 'boolean', 'rules' => ['boolean']],
            ['name' => 'on_sale', 'location' => 'query', 'type' => 'boolean', 'rules' => ['boolean']],
            ['name' => 'featured', 'location' => 'query', 'type' => 'boolean', 'rules' => ['boolean']],
            ['name' => 'is_new', 'location' => 'query', 'type' => 'boolean', 'rules' => ['boolean']],
            [
                'name' => 'sort', 'location' => 'query', 'type' => 'string',
                'default_value' => 'newest',
                'rules' => ['string', 'in:relevance,newest,price_asc,price_desc,name_asc,name_desc'],
            ],
            ['name' => 'direction', 'location' => 'query', 'type' => 'string', 'rules' => ['string', 'in:asc,desc']],
            ['name' => 'per_page', 'location' => 'query', 'type' => 'integer', 'rules' => ['integer', 'min:1', 'max:60']],
            ['name' => 'page', 'location' => 'query', 'type' => 'integer', 'rules' => ['integer', 'min:1']],
        ]);

        $detail = $this->upsertEndpoint($definition, [
            'method' => 'GET',
            'path' => 'store/products/{product}',
            'action' => StoreCatalogService::class.'::product',
            'handler_type' => 'service',
            'description' => 'Complete product record: images, variants, taxonomy, attributes and review summary.',
            'requires_auth' => false,
            'sort_order' => 20,
        ]);

        $this->syncParameters($detail, [
            ['name' => 'product', 'location' => 'path', 'type' => 'string', 'required' => true, 'rules' => ['string', 'max:255']],
        ]);

        $filters = $this->upsertEndpoint($definition, [
            'method' => 'GET',
            'path' => 'store/filters',
            'action' => StoreCatalogService::class.'::filters',
            'handler_type' => 'service',
            'description' => 'Filter facets for building catalogue filter UI: price bounds, categories, brands, availability.',
            'requires_auth' => false,
            'sort_order' => 30,
        ]);

        $this->syncParameters($filters, []);
    }

    /**
     * Admin endpoints: `requires_auth` plus an `orders.view` grant, so a valid
     * token alone is not enough — the principal must also hold the permission.
     */
    protected function registerAdminEndpoints(ApiDefinition $definition): void
    {
        $permission = Permission::where('name', 'orders.view')->first();

        $orders = $this->upsertEndpoint($definition, [
            'method' => 'GET',
            'path' => 'admin/orders',
            'action' => AdminOrdersService::class.'::orders',
            'handler_type' => 'service',
            'description' => 'Paginated order list with status, payment, fulfilment, date-range and customer filters.',
            'requires_auth' => true,
            'sort_order' => 10,
        ]);

        $this->syncParameters($orders, [
            ['name' => 'status', 'location' => 'query', 'type' => 'string', 'rules' => ['string', 'in:'.implode(',', $this->enumValues(OrderStatus::class))]],
            ['name' => 'payment_status', 'location' => 'query', 'type' => 'string', 'rules' => ['string', 'in:'.implode(',', $this->enumValues(PaymentStatus::class))]],
            ['name' => 'fulfillment_status', 'location' => 'query', 'type' => 'string', 'rules' => ['string', 'in:'.implode(',', $this->enumValues(FulfillmentStatus::class))]],
            ['name' => 'search', 'location' => 'query', 'type' => 'string', 'rules' => ['string', 'max:120']],
            ['name' => 'placed_from', 'location' => 'query', 'type' => 'string', 'rules' => ['date']],
            ['name' => 'placed_to', 'location' => 'query', 'type' => 'string', 'rules' => ['date']],
            ['name' => 'per_page', 'location' => 'query', 'type' => 'integer', 'rules' => ['integer', 'min:1', 'max:100']],
            ['name' => 'page', 'location' => 'query', 'type' => 'integer', 'rules' => ['integer', 'min:1']],
        ]);

        $detail = $this->upsertEndpoint($definition, [
            'method' => 'GET',
            'path' => 'admin/orders/{order}',
            'action' => AdminOrdersService::class.'::order',
            'handler_type' => 'service',
            'description' => 'Complete order record: line items, addresses, totals and status history.',
            'requires_auth' => true,
            'sort_order' => 20,
        ]);

        $this->syncParameters($detail, [
            ['name' => 'order', 'location' => 'path', 'type' => 'string', 'required' => true, 'rules' => ['string', 'max:255']],
        ]);

        // Both admin endpoints share the same permission. Attached to each
        // endpoint rather than the definition, because the dispatcher
        // evaluates grants per endpoint.
        foreach ([$orders, $detail] as $endpoint) {
            ApiEndpointPermission::updateOrCreate(
                ['api_endpoint_id' => $endpoint->getKey(), 'permission_id' => $permission?->getKey()],
                ['access_type' => 'allow'],
            );
        }
    }

    /**
     * Create or refresh one endpoint within a definition, reviving it if deleted.
     *
     * Matched on (definition, method, path) rather than on `action`, so
     * repointing a handler rewrites the existing route instead of orphaning
     * the old one and leaving a duplicate path to trip the unique index.
     * `withTrashed()` for the same reason as upsertDefinition: a soft-deleted
     * endpoint still occupies its slot in the unique index.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertEndpoint(ApiDefinition $definition, array $attributes): ApiEndpoint
    {
        $existing = ApiEndpoint::withTrashed()
            ->where('api_definition_id', $definition->getKey())
            ->where('method', $attributes['method'])
            ->where('path', $attributes['path'])
            ->first();

        if ($existing !== null) {
            $existing->restore();
            $existing->fill($attributes)->save();

            return $existing;
        }

        return ApiEndpoint::create($attributes + ['api_definition_id' => $definition->getKey()]);
    }

    /**
     * Replace an endpoint's parameter set with the declared one.
     *
     * Parameters are a full declaration, not a patch: an endpoint whose filter
     * set changed must not keep validating a removed query parameter. Rows are
     * keyed by name so ordering is stable.
     *
     * @param  list<array<string, mixed>>  $parameters
     */
    protected function syncParameters(ApiEndpoint $endpoint, array $parameters): void
    {
        $endpoint->parameters()->delete();

        foreach ($parameters as $sortOrder => $parameter) {
            $endpoint->parameters()->create([
                'name' => $parameter['name'],
                'location' => $parameter['location'],
                'type' => $parameter['type'],
                'required' => (bool) ($parameter['required'] ?? false),
                'default_value' => $parameter['default_value'] ?? null,
                'description' => $parameter['description'] ?? null,
                'rules' => json_encode(array_values($parameter['rules'] ?? [])),
                'sort_order' => $sortOrder,
            ]);
        }
    }

    /**
     * Backed enum values, as a plain list of strings.
     *
     * @param  class-string<\BackedEnum>  $enum
     * @return list<string>
     */
    protected function enumValues(string $enum): array
    {
        return array_map(fn ($case) => (string) $case->value, $enum::cases());
    }
}
