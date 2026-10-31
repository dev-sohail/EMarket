<?php

namespace App\Http\Resources\Api;

use App\Enums\StockStatus;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact product shape for catalog listings.
 *
 * Cost price is excluded: Product already hides it, and a listing that leaked
 * it would turn the public storefront API into a margin report. Variants and
 * full copy belong to ProductDetailResource.
 *
 * @property-read Product $resource
 */
class ProductSummaryResource extends JsonResource
{
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
            'price' => $this->resource->final_price,
            'list_price' => $this->resource->list_price,
            'on_sale' => $this->resource->final_price < $this->resource->list_price,
            'currency_symbol' => currency_symbol(),
            'stock_status' => $this->resource->stock_status?->value,
            'in_stock' => $this->resource->stock_status === StockStatus::InStock,
            'is_featured' => (bool) $this->resource->is_featured,
            'is_new' => (bool) $this->resource->is_new,
            'rating' => $this->resource->average_rating,
            'image' => $this->resource->primary_image,
            'category' => $this->whenLoaded('categories', fn () => $this->resource->categories->first()?->only(['_id', 'name', 'slug'])),
            'brand' => $this->whenLoaded('brands', fn () => $this->resource->brands->first()?->only(['_id', 'name', 'slug'])),
        ];
    }
}
