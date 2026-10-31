<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends PrefixedModel
{
    use HasFactory;

    protected $table = 'shipping_methods';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'base_cost',
        'per_kg_cost',
        'estimated_days',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_cost' => 'decimal:2',
            'per_kg_cost' => 'decimal:2',
            'estimated_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    protected function getIdPrefix(): string
    {
        return 'shm';
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ShippingRule::class);
    }

    /**
     * Calculate the base rate for this shipping method before any location-specific rules apply.
     * This is used to display an initial rate on the checkout page before the user enters their city.
     */
    public function calculateRate(float $weight, float $orderValue, ?int $quantity = null): float
    {
        return (float) $this->base_cost + ($weight * (float) ($this->per_kg_cost ?? 0));
    }
}
