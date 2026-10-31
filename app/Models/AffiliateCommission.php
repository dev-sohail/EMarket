<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends PrefixedModel
{
    use HasFactory;

    protected $table = 'affiliate_commissions';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'affiliate_id',
        'order_id',
        'amount',
        'rate',
        'status',
        'reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'affiliate_id' => 'string',
            'order_id' => 'string',
            'amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'afc';
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
