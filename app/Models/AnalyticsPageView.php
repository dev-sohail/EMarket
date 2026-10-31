<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsPageView extends PrefixedModel
{
    use HasFactory;

    protected $table = 'analytics_page_views';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'visitor_id',
        'session_uuid',
        'path',
        'title',
        'viewed_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'visitor_id' => 'string',
            'viewed_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'apv';
    }
}
