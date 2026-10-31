<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends PrefixedModel
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'string',
            'entity_id' => 'string',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'aud';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
