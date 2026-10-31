<?php

namespace App\Models;

use App\Traits\PrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends PrefixedModel
{
    use HasFactory, PrefixedId;

    protected $table = 'api_tokens';

    protected $fillable = [
        'name',
        'user_id',
        'token',
        'tokenable_type',
        'tokenable_id',
        'last_used_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'tok';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function tokenable(): BelongsTo
    {
        return $this->morphTo();
    }
}
