<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCharge extends PrefixedModel
{
    use HasFactory;

    protected $table = 'order_charges';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'charge_type',
        'name',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'string',
            'amount' => 'decimal:2',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'och';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
