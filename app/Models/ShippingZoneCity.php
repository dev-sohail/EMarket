<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingZoneCity extends PrefixedModel
{
    use HasFactory;

    protected $table = 'shipping_zone_cities';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'shipping_zone_id',
        'city',
    ];

    protected function casts(): array
    {
        return [
            'shipping_zone_id' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'szc';
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }
}
