<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends PrefixedModel
{
    use HasFactory;

    protected $table = 'coupon_usages';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'order_id',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'coupon_id' => 'string',
            'customer_id' => 'string',
            'order_id' => 'string',
            'used_at' => 'datetime',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'cus';
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
