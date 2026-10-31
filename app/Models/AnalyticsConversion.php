<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsConversion extends PrefixedModel
{
    use HasFactory;

    protected $table = 'analytics_conversions';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'visitor_id',
        'session_uuid',
        'order_id',
        'customer_id',
        'revenue',
        'conversion_type',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'visitor_id' => 'string',
            'order_id' => 'string',
            'customer_id' => 'string',
            'revenue' => 'decimal:2',
            'converted_at' => 'datetime',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'anc';
    }
}
