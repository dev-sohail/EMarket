<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends PrefixedModel
{
    use HasFactory;

    protected $table = 'invoice_items';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'invoice_id',
        'order_item_id',
        'product_name',
        'sku',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'invoice_id' => 'string',
            'order_item_id' => 'string',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'iin';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
