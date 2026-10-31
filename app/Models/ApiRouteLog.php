<?php

namespace App\Models;

use App\Traits\PrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRouteLog extends PrefixedModel
{
    use HasFactory, PrefixedId;

    protected $table = 'api_route_logs';

    // api_route_logs is append-only: it records what happened, never what was
    // later changed. The migration only defines created_at, so timestamps are
    // disabled to stop Eloquent writing an updated_at that does not exist.
    public $timestamps = false;

    protected $fillable = [
        'api_endpoint_id',
        'method',
        'path',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'user_id',
        'request_payload',
        'response_payload',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'arlog';
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ApiEndpoint::class, 'api_endpoint_id', '_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }
}
