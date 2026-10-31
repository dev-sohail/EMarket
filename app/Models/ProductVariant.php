<?php

namespace App\Models;

use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_variants';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'price',
        'price_add_to_main',
        'sale_price',
        'cost_price',
        'stock_quantity',
        'stock_status',
        'weight',
        'is_active',
        'image',
        'combination_key',
    ];

    protected $hidden = ['cost_price'];

    protected function casts(): array
    {
        return [
            'product_id' => 'string',
            'price' => 'decimal:2',
            'price_add_to_main' => 'integer',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'stock_status' => StockStatus::class,
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ProductVariant $variant) {
            $variant->attributeValues()->detach();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'variant_attribute_values', 'product_variant_id', 'attribute_value_id')
            ->withPivot('attribute_id')
            ->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'variant_id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class, 'variant_id');
    }

    public function getListPriceAttribute(): float
    {
        $price = (float) ($this->price ?? 0);

        if ($this->price_add_to_main) {
            $price += (float) ($this->product?->base_price ?? 0);
        }

        return round($price, 2);
    }

    public function getFinalPriceAttribute(): float
    {
        $price = (float) ($this->price ?? 0);

        if ($this->sale_price !== null && (float) $this->sale_price < $price) {
            $price = (float) $this->sale_price;
        }

        if ($this->price_add_to_main) {
            $price += (float) ($this->product?->base_price ?? 0);
        }

        return round($price, 2);
    }

    public function getStockAttribute(): int
    {
        return (int) ($this->stock_quantity ?? 0);
    }

    protected function getIdPrefix(): string
    {
        return 'pvt';
    }
}
