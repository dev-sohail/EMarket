<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsVisitor extends PrefixedModel
{
    use HasFactory;

    protected $table = 'analytics_visitors';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uuid',
        'country',
        'device_type',
        'browser',
        'referrer_domain',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'anv';
    }
}
