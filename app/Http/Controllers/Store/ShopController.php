<?php

namespace App\Http\Controllers\Store;

use App\Enums\AttributeType;
use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DiscountCampaign;
use App\Models\FlashSale;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use App\Services\Review\ReviewService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected ReviewService $reviewService
    ) {}

    public function index(Request $request)
    {
        // Special case: handle best_selling sort directly with getBestSellers()
        if ($request->get('sort') === 'best_selling') {
            $products = $this->productService->getBestSellers(12);

            $filterableAttributes = $this->filterableAttributes();
            $filters = $request->only([
                'q', 'categories', 'brands', 'min_price', 'max_price',
                'is_featured', 'is_new', 'sale', 'sort', 'per_page',
            ]);

            return view('store.shop', compact('products', 'filters', 'filterableAttributes'));
        }

        $filters = $request->only([
            'q', 'categories', 'brands', 'min_price', 'max_price',
            'is_featured', 'is_new', 'sale', 'sort', 'per_page',
        ]);

        $filters['search'] = $filters['q'] ?? null;
        $filters['category_id'] = $this->resolveSlugsToIds('categories');
        $filters['brand_id'] = $this->resolveSlugsToIds('brands');
        $filters['attribute_value_ids'] = $this->resolveAttributeValues($request->input('attributes', []));
        $filters['sort_by'] = $this->resolveSortBy($filters['sort'] ?? null);
        $filters['sort_dir'] = $this->resolveSortDir($filters['sort'] ?? null);

        $products = $this->productService->search($filters);

        $filterableAttributes = $this->filterableAttributes();

        return view('store.shop', compact('products', 'filters', 'filterableAttributes'));
    }

    /**
     * The attributes offered as catalogue filters, in admin-defined order.
     *
     * Only types a shopper can meaningfully choose between are listed: a text
     * or number attribute has no fixed option set, so it can be searched but
     * not offered as a checkbox group.
     *
     * @return Collection<int, Attribute>
     */
    protected function filterableAttributes(): Collection
    {
        return Attribute::query()
            ->where('is_filterable', true)
            ->whereIn('type', [
                AttributeType::Select->value,
                AttributeType::Multiselect->value,
                AttributeType::Color->value,
            ])
            ->with('values')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Turn `attributes[<slug>][<value id>]` into value ids grouped by attribute.
     *
     * Only attributes the operator marked filterable are honoured. The check is
     * not redundant: the parameter arrives from the URL, so without it a
     * hand-edited link could filter the catalogue on an attribute that is not
     * meant to be exposed, and value ids from a different attribute would
     * silently broaden the result.
     *
     * @return array<string, array<int, string>>
     */
    protected function resolveAttributeValues(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $resolved = [];

        foreach ($raw as $attributeSlug => $valueIds) {
            $valueIds = is_array($valueIds) ? $valueIds : [$valueIds];
            $valueIds = array_values(array_filter(array_map('strval', $valueIds)));

            if ($valueIds === []) {
                continue;
            }

            $attribute = Attribute::query()
                ->where('slug', $attributeSlug)
                ->where('is_filterable', true)
                ->first();

            if ($attribute === null) {
                continue;
            }

            // Restrict to this attribute's own values so an id belonging to a
            // different attribute cannot widen the match.
            $allowed = $attribute->values()->pluck('_id')->all();
            $matching = array_values(array_intersect($valueIds, $allowed));

            if ($matching !== []) {
                $resolved[$attributeSlug] = $matching;
            }
        }

        return $resolved;
    }

    protected function resolveSlugsToIds(string $key): ?array
    {
        $slugs = request($key, []);
        if (empty($slugs)) {
            return null;
        }
        $slugs = is_array($slugs) ? $slugs : [$slugs];
        $model = $key === 'categories' ? Category::class : Brand::class;

        return $model::whereIn('slug', $slugs)->pluck('_id')->toArray();
    }

    protected function resolveSortBy(?string $sort): ?string
    {
        return match ($sort) {
            'price_asc', 'price_desc' => 'base_price',
            'name_asc', 'name_desc' => 'name',
            'newest' => 'created_at',
            default => null,
        };
    }

    protected function resolveSortDir(?string $sort): ?string
    {
        return match ($sort) {
            'price_asc', 'name_asc' => 'asc',
            'price_desc', 'name_desc' => 'desc',
            'newest' => 'desc',
            default => null,
        };
    }

    public function show(Product $product)
    {
        $product->load([
            'categories', 'brands', 'tags', 'images', 'attributeValues.attribute',
            'variants.images', 'variants.product', 'reviews' => function ($q) {
                $q->approved()->with('customer')->latest();
            },
        ]);

        $product->loadCount(['reviews as reviews_count' => fn ($q) => $q->approved()]);

        $relatedProducts = Product::with(['images', 'variants'])
            ->published()
            ->whereHas('categories', fn ($q) => $q->whereIn('categories._id', $product->categories->pluck('_id')))
            ->where('_id', '!=', $product->getKey())
            ->limit(4)
            ->get();

        $averageRating = $product->average_rating;
        $reviewCount = $product->reviews_count;

        // `$product->flashSales()->first()` runs a plain relation query, so the result
        // carries no pivot — the product page reads $activeFlashSale->pivot for
        // the per-product price and silently fell back to the sale-wide
        // sale_price. Querying through the pivot keeps that price available.
        $activeFlashSale = FlashSale::query()
            ->where('flash_sales.is_active', true)
            ->where('flash_sales.starts_at', '<=', now())
            ->where('flash_sales.ends_at', '>=', now())
            ->whereHas('products', fn ($q) => $q->where('products._id', $product->getKey()))
            ->with(['products' => fn ($q) => $q->where('products._id', $product->getKey())])
            ->first();

        $activeCampaign = DiscountCampaign::query()
            ->where('discount_campaigns.is_active', true)
            ->where('discount_campaigns.starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('discount_campaigns.ends_at')->orWhere('discount_campaigns.ends_at', '>=', now());
            })
            ->whereHas('products', fn ($q) => $q->where('products._id', $product->getKey()))
            ->with(['products' => fn ($q) => $q->where('products._id', $product->getKey())])
            ->first();

        // The promotion price lives on the join row for THIS product, not on the
        // sale or campaign model — a relation query returns the model without it,
        // so the product page's `$sale->pivot->flash_price` read was always null
        // and every promotion fell back to its sale-wide price.
        $flashPrice = $activeFlashSale?->products->first()?->pivot?->flash_price;
        $campaignPrice = $activeCampaign?->products->first()?->pivot?->campaign_price;

        return view('store.product.show', compact(
            'product', 'relatedProducts', 'averageRating', 'reviewCount',
            'activeFlashSale', 'activeCampaign', 'flashPrice', 'campaignPrice'
        ));
    }
}
