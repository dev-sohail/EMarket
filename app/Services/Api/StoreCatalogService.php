<?php

namespace App\Services\Api;

use App\Enums\StockStatus;
use App\Http\Resources\Api\ProductDetailResource;
use App\Http\Resources\Api\ProductSummaryResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only storefront catalogue served through the dynamic API.
 *
 * Every method here is a `service`-type handler: ApiDynamicController passes
 * the validated parameters in on the request, so each method reads its inputs
 * from `api_validated` rather than from the raw query string. That matters —
 * a handler reading $request->query() directly would bypass the declared
 * validation rules the endpoint advertises.
 *
 * Nothing here mutates, and nothing here trusts the caller: the catalogue is
 * public, so `published()` is applied unconditionally and cost price never
 * leaves the process.
 */
class StoreCatalogService
{
    /**
     * Sort keys the public API accepts, mapped to real columns.
     *
     * An allow-list rather than passing the client's string to orderBy: the
     * latter is a SQL injection seam on any column the caller can name.
     *
     * @var array<string, string>
     */
    protected const SORTS = [
        'relevance' => 'created_at',
        'newest' => 'created_at',
        'price_asc' => 'base_price',
        'price_desc' => 'base_price',
        'name_asc' => 'name',
        'name_desc' => 'name',
    ];

    /**
     * Hard ceiling on `per_page`.
     *
     * A caller asking for 10 000 rows is answered with the maximum instead of
     * the requested amount; a public endpoint must not let the query decide.
     */
    protected const MAX_PER_PAGE = 60;

    /**
     * GET store/products — filterable, paginated catalogue listing.
     *
     * Pagination metadata is returned explicitly rather than relying on the
     * ResourceCollection's own `meta` key: ApiDynamicController wraps whatever
     * a handler returns under its own `data`/`meta` envelope, which flattened
     * the collection and discarded the page counters. Without this, a client
     * cannot tell there is a page two.
     *
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function products(Request $request): array
    {
        $validated = $this->validated($request);

        $query = $this->catalogQuery()
            ->with([
                // The related key must be `_id`, not `id`: ProductImage is a
                // prefixed model, and an explicit column list has to name the
                // related model's own primary key or Eloquent selects a column
                // that does not exist and the whole listing 500s.
                'images:_id,product_id,path,alt_text,is_primary,sort_order',
                'categories',
                'brands',
            ]);

        $this->applyFilters($query, $validated);

        $sort = (string) ($validated['sort'] ?? 'newest');

        $paginator = $query
            ->orderBy(self::SORTS[$sort] ?? self::SORTS['newest'], $this->sortDirection($sort, $validated['direction'] ?? null))
            ->paginate($this->perPage($validated['per_page'] ?? null));

        return [
            'items' => ProductSummaryResource::collection($paginator)->resolve($request),
            'pagination' => $this->pagination($paginator),
        ];
    }

    /**
     * GET store/products/{product} — complete product detail.
     *
     * Resolved by slug (what a storefront links to) with the prefixed id as a
     * fallback, so a caller holding either identifier gets the same record.
     *
     *
     * @throws NotFoundHttpException
     */
    public function product(Request $request): ProductDetailResource
    {
        $validated = $this->validated($request);
        $identifier = (string) ($validated['product'] ?? '');

        $product = $this->catalogQuery()
            ->with(ProductDetailResource::RELATIONS)
            ->where(function (Builder $query) use ($identifier) {
                $query->where('slug', $identifier)->orWhere('_id', $identifier);
            })
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }

        return new ProductDetailResource($product);
    }

    /**
     * GET store/filters — the facets a storefront needs to build filter UI.
     *
     * Returned from the live catalogue rather than a static list so the values
     * always match what is actually purchasable, including the real price
     * bounds. Counted with the same `catalogQuery()` scope as the listing, or
     * the facet counts would include unpublished products the listing hides.
     *
     * @return array<string, mixed>
     */
    public function filters(Request $request): array
    {
        $base = $this->catalogQuery();

        $priceBounds = (clone $base)
            ->selectRaw('MIN(base_price) as min_price, MAX(base_price) as max_price')
            ->first();

        return [
            'price' => [
                'min' => $priceBounds?->min_price !== null ? (float) $priceBounds->min_price : 0.0,
                'max' => $priceBounds?->max_price !== null ? (float) $priceBounds->max_price : 0.0,
                'currency_symbol' => currency_symbol(),
            ],
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['_id', 'name', 'slug'])
                ->map(fn (Category $category) => [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'product_count' => $this->countFor($base, 'categories', $category->getKey()),
                ]),
            'brands' => $this->brandFacets($base),
            'availability' => [
                ['value' => StockStatus::InStock->value, 'label' => 'In stock'],
                ['value' => StockStatus::OnBackorder->value, 'label' => 'On backorder'],
                ['value' => StockStatus::OutOfStock->value, 'label' => 'Out of stock'],
            ],
            'sorts' => array_keys(self::SORTS),
        ];
    }

    /**
     * The base scope every catalogue read starts from.
     *
     * `published()` is non-negotiable here: it is what keeps draft and
     * future-dated products out of the public API.
     */
    protected function catalogQuery(): Builder
    {
        return Product::query()->published();
    }

    /**
     * Apply the declared filter parameters to the listing query.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function applyFilters(Builder $query, array $validated): void
    {
        if (! empty($validated['search'])) {
            $search = (string) $validated['search'];

            $query->where(function (Builder $sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['category'])) {
            $query->whereHas('categories', fn (Builder $sub) => $sub->where('slug', $validated['category']));
        }

        if (! empty($validated['brand'])) {
            $query->whereHas('brands', fn (Builder $sub) => $sub->where('slug', $validated['brand']));
        }

        // Priced against the effective price, not base_price: filtering by
        // "under 50" must not drop a product that a discount brought under 50.
        //
        // Expressed as plain column comparisons rather than a SQL expression
        // such as COALESCE(NULLIF(sale_price,0), base_price). The expression
        // binds the bound value as a string, and SQLite orders TEXT above every
        // NUMERIC, so `250 >= '100'` evaluated false and the filter silently
        // returned nothing. These two comparisons keep the same meaning and
        // stay portable across MySQL, SQLite and PostgreSQL.
        if (isset($validated['min_price'])) {
            $this->applyEffectivePriceConstraint($query, '>=', (float) $validated['min_price']);
        }

        if (isset($validated['max_price'])) {
            $this->applyEffectivePriceConstraint($query, '<=', (float) $validated['max_price']);
        }

        if (! empty($validated['in_stock'])) {
            $query->inStock();
        }

        if (! empty($validated['on_sale'])) {
            $query->onSale();
        }

        if (! empty($validated['featured'])) {
            $query->featured();
        }

        if (! empty($validated['is_new'])) {
            $query->new();
        }
    }

    /**
     * Constrain products by the price a shopper actually pays.
     *
     * The effective price is `sale_price` when it is set and genuinely lower
     * than `base_price` (mirroring Product::onSale and the final_price
     * accessor), and `base_price` otherwise. Expressed as
     * `base_price <op> $price OR (sale_price is set AND sale_price < base_price AND sale_price <op> $price)`
     * so the comparison stays on indexed columns.
     *
     * @param  '>='|'<='  $operator
     */
    protected function applyEffectivePriceConstraint(Builder $query, string $operator, float $price): void
    {
        $query->where(function (Builder $query) use ($operator, $price) {
            $query->where('base_price', $operator, $price)
                ->orWhere(function (Builder $discounted) use ($operator, $price) {
                    $discounted->whereNotNull('sale_price')
                        ->whereColumn('sale_price', '<', 'base_price')
                        ->where('sale_price', $operator, $price);
                });
        });
    }

    /**
     * Resolve the ORDER BY direction.
     *
     * An explicit `direction` parameter wins so the storefront can offer
     * "price, then flip the arrow"; otherwise the direction implied by the
     * sort key is used, so `price_desc` is descending on its own.
     */
    protected function sortDirection(string $sort, mixed $requested): string
    {
        if (in_array($requested, ['asc', 'desc'], true)) {
            return $requested;
        }

        return str_ends_with($sort, '_desc') || in_array($sort, ['newest', 'relevance'], true)
            ? 'desc'
            : 'asc';
    }

    /**
     * Clamp `per_page` into 1..MAX_PER_PAGE.
     */
    protected function perPage(mixed $requested): int
    {
        $perPage = (int) ($requested ?: 20);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Page counters a client needs to drive a pager.
     *
     * @return array<string, int>
     */
    protected function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ];
    }

    /**
     * Count catalogue products attached to one pivot row.
     *
     * @param  class-string  $relation
     */
    protected function countFor(Builder $base, string $relation, string $id): int
    {
        return (clone $base)->whereHas($relation, fn (Builder $sub) => $sub->where('_id', $id))->count();
    }

    /**
     * Brand facets with live counts.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function brandFacets(Builder $base): Collection
    {
        return Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['_id', 'name', 'slug'])
            ->map(fn ($brand) => [
                'id' => $brand->getKey(),
                'name' => $brand->name,
                'slug' => $brand->slug,
                'product_count' => $this->countFor($base, 'brands', $brand->getKey()),
            ]);
    }

    /**
     * Read the parameters ApiDynamicController validated for this request.
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return (array) $request->attributes->get('api_validated', []);
    }
}
