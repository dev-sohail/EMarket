<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends PrefixedModel
{
    use HasFactory;

    protected $table = 'order_items';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'sku',
        'variant_name',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'string',
            'product_id' => 'string',
            'variant_id' => 'string',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'oit';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', '_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', '_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', '_id');
    }
}
