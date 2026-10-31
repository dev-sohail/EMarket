<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Affiliate extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'affiliates';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'code',
        'status',
        'commission_rate',
        'clicks',
        'total_earned',
        'total_paid',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'string',
            'commission_rate' => 'decimal:2',
            'clicks' => 'integer',
            'total_earned' => 'decimal:2',
            'total_paid' => 'decimal:2',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    protected function getIdPrefix(): string
    {
        return 'aff';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }
}
