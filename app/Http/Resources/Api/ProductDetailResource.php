<?php

namespace App\Http\Resources\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Complete storefront view of one product.
 *
 * Loads the relations a shopper needs to make a decision — imagery, variant
 * matrix, taxonomy, attributes and review summary — in one round trip, so the
 * storefront does not fan out into six follow-up requests.
 *
 * @property-read Product $resource
 */
class ProductDetailResource extends JsonResource
{
    /**
     * Relations the service must eager-load for this shape to be complete.
     *
     * Declared here rather than in the service so the contract between the two
     * cannot drift: adding a field that needs a relation without adding it to
     * this list would silently ship `null`.
     *
     * @var list<string>
     */
    public const RELATIONS = [
        'images',
        'variants',
        'categories',
        'brands',
        'tags',
        'attributeValues',
        'reviews',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'sku' => $this->resource->sku,
            'short_description' => $this->resource->short_description,
            'description' => $this->resource->description,
            'price' => $this->resource->final_price,
            'list_price' => $this->resource->list_price,
            'currency_symbol' => currency_symbol(),
            'discount_type' => $this->resource->discount_type,
            'discount_value' => $this->resource->discount_value,
            'stock_status' => $this->resource->stock_status?->value,
            'stock_quantity' => $this->resource->stock_quantity,
            'is_active' => (bool) $this->resource->is_active,
            'is_featured' => (bool) $this->resource->is_featured,
            'is_new' => (bool) $this->resource->is_new,
            'return_allowed' => (bool) $this->resource->return_allowed,
            'meta_title' => $this->resource->meta_title,
            'meta_description' => $this->resource->meta_description,
            'published_at' => $this->resource->published_at?->toIso8601String(),
            'specifications' => $this->resource->specifications,
            'weight' => $this->resource->weight,
            'dimensions' => [
                'length' => $this->resource->length,
                'width' => $this->resource->width,
                'height' => $this->resource->height,
            ],
            'images' => $this->whenLoaded('images', fn () => $this->resource->images
                ->sortByDesc('is_primary')
                ->values()
                ->map(fn ($image) => [
                    'id' => $image->getKey(),
                    'url' => $image->path,
                    'alt' => $image->alt_text,
                    'is_primary' => (bool) $image->is_primary,
                ])),
            'variants' => $this->whenLoaded('variants', fn () => $this->resource->variants
                ->map(fn ($variant) => [
                    'id' => $variant->getKey(),
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'price' => (float) $variant->price,
                    'sale_price' => $variant->sale_price !== null ? (float) $variant->sale_price : null,
                    'stock_status' => $variant->stock_status,
                    'stock_quantity' => $variant->stock_quantity,
                    'image' => $variant->image,
                ])),
            'categories' => $this->whenLoaded('categories', fn () => $this->resource->categories
                ->map(fn ($category) => [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])),
            'brands' => $this->whenLoaded('brands', fn () => $this->resource->brands
                ->map(fn ($brand) => [
                    'id' => $brand->getKey(),
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                ])),
            'tags' => $this->whenLoaded('tags', fn () => $this->resource->tags
                ->map(fn ($tag) => [
                    'id' => $tag->getKey(),
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])),
            'attributes' => $this->whenLoaded('attributeValues', fn () => $this->resource->attributeValues
                ->map(fn ($value) => [
                    'attribute_id' => $value->pivot->attribute_id,
                    'id' => $value->getKey(),
                    'value' => $value->value,
                ])),
            'rating' => $this->whenLoaded('reviews', fn () => [
                'average' => $this->resource->average_rating,
                'count' => $this->resource->reviews->where('status', 'approved')->count(),
            ]),
        ];
    }
}
