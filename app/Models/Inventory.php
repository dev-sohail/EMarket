<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends PrefixedModel
{
    use HasFactory;

    protected $table = 'inventories';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'variant_id',
        'quantity',
        'reserved_quantity',
        'reorder_level',
        'max_stock',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'string',
            'variant_id' => 'string',
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'reorder_level' => 'integer',
            'max_stock' => 'integer',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'ist';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
