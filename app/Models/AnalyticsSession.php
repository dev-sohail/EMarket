<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSession extends PrefixedModel
{
    use HasFactory;

    protected $table = 'analytics_sessions';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'visitor_id',
        'session_uuid',
        'started_at',
        'ended_at',
        'page_view_count',
        'duration_seconds',
        'bounced',
    ];

    protected function casts(): array
    {
        return [
            'visitor_id' => 'string',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'page_view_count' => 'integer',
            'duration_seconds' => 'integer',
            'bounced' => 'boolean',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(AnalyticsVisitor::class);
    }

    protected function getIdPrefix(): string
    {
        return 'ans';
    }
}
