<?php

namespace App\Models;

use App\Traits\PrefixedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiDefinition extends PrefixedModel
{
    use HasFactory, PrefixedId, SoftDeletes;

    protected $table = 'api_definitions';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'source',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'apid';
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(ApiEndpoint::class, 'api_definition_id', '_id');
    }

    public function activeEndpoints(): HasMany
    {
        return $this->endpoints()->where('is_active', true);
    }
}
