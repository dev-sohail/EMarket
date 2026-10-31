<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends PrefixedModel
{
    use HasFactory;

    protected $table = 'payment_methods';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'is_cod',
        'sort_order',
        'stripe_enabled',
        'stripe_sandbox',
        'stripe_publishable_key',
        'stripe_secret_key',
    ];

    protected $hidden = [
        'stripe_secret_key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_cod' => 'boolean',
            'sort_order' => 'integer',
            'stripe_enabled' => 'boolean',
            'stripe_sandbox' => 'boolean',
            'stripe_secret_key' => 'encrypted',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    protected function getIdPrefix(): string
    {
        return 'pym';
    }
}
