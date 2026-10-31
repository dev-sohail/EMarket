<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends PrefixedModel
{
    use HasFactory;

    protected $table = 'return_items';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'return_id',
        'order_item_id',
        'return_reason_id',
        'quantity',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'return_id' => 'string',
            'order_item_id' => 'string',
            'return_reason_id' => 'string',
            'quantity' => 'integer',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'rit';
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function returnReason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'return_reason_id');
    }
}
