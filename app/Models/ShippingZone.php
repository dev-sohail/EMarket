<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends PrefixedModel
{
    use HasFactory;

    protected $table = 'shipping_zones';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'shz';
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ShippingRule::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(ShippingZoneCity::class);
    }
}
