<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends PrefixedModel
{
    use HasFactory;

    protected $table = 'order_addresses';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'type',
        'full_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'oad';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
