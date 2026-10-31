<?php

namespace App\Models;

use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'base_price',
        'sale_price',
        'cost_price',
        'discount_type',
        'discount_value',
        'stock_status',
        'stock_quantity',
        'weight',
        'length',
        'width',
        'height',
        'is_active',
        'is_featured',
        'is_new',
        'return_allowed',
        'meta_title',
        'meta_description',
        'published_at',
        'specifications',
    ];

    protected $hidden = [
        'cost_price',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'return_allowed' => 'boolean',
            'published_at' => 'datetime',
            'discount_type' => 'string',
            'discount_value' => 'decimal:2',
            'specifications' => 'array',
            'stock_status' => StockStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Product $product) {
            $product->categories()->detach();
            $product->brands()->detach();
            $product->tags()->detach();
            $product->attributeValues()->detach();
            $product->flashSales()->detach();
            $product->campaigns()->detach();
        });
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories', 'product_id', 'category_id');
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'product_brands', 'product_id', 'brand_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags', 'product_id', 'tag_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values', 'product_id', 'attribute_value_id')
            ->withPivot(['attribute_id'])
            ->withTimestamps();
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id', '_id');
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, OrderItem::class, 'product_id', '_id', '_id', 'order_id');
    }

    public function flashSales(): BelongsToMany
    {
        return $this->belongsToMany(FlashSale::class, 'flash_sale_products', 'product_id', 'flash_sale_id')
            ->withPivot(['flash_price', 'quantity', 'sold'])
            ->withTimestamps();
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(DiscountCampaign::class, 'campaign_products', 'product_id', 'campaign_id')
            ->withPivot(['campaign_price']);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('is_new', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('stock_status', 'in_stock')
                ->orWhereHas('variants', fn (Builder $q2) => $q2->where('stock_status', 'in_stock'));
        });
    }

    public function scopeOnSale(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(function (Builder $subQ) {
                $subQ->whereNotNull('sale_price')
                    ->whereColumn('sale_price', '<', 'base_price');
            })->orWhere(function (Builder $subQ) {
                $subQ->whereIn('discount_type', ['percent', 'fixed'])
                    ->where('discount_value', '>', 0);
            });
        });
    }

    public function getPrimaryImageAttribute(): ?string
    {
        // Honour the eager-loaded relation. The list views load `images`
        // precisely for this column, and querying here instead cost two
        // queries per row — 30 extra queries on a 15-row page.
        $images = $this->relationLoaded('images')
            ? $this->images
            : $this->images()->get();

        $image = $images->firstWhere('is_primary', true) ?? $images->first();

        return $image?->path;
    }

    public function getAverageRatingAttribute(): ?float
    {
        if ($this->relationLoaded('reviews')) {
            $avg = $this->reviews->where('status', 'approved')->avg('rating');

            return $avg ? round($avg, 1) : null;
        }

        $avg = $this->reviews()->where('status', 'approved')->avg('rating');

        return $avg ? round($avg, 1) : null;
    }

    public function getFinalPriceAttribute(): float
    {
        if ($this->sale_price !== null && $this->sale_price < $this->base_price) {
            return (float) $this->sale_price;
        }

        $price = $this->base_price;

        if ($this->discount_type === 'percent' && $this->discount_value > 0) {
            // A percent discount above 100 would otherwise drive the price
            // negative; the fixed branch already floors at zero.
            return max(0, round($price * (1 - $this->discount_value / 100), 2));
        }
        if ($this->discount_type === 'fixed' && $this->discount_value > 0) {
            return max(0, round($price - $this->discount_value, 2));
        }

        return (float) $price;
    }

    public function getListPriceAttribute(): float
    {
        return (float) ($this->base_price ?? 0);
    }

    protected function getIdPrefix(): string
    {
        return 'prd';
    }
}
