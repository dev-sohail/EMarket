<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Banner extends PrefixedModel
{
    use HasFactory;

    protected $table = 'banners';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'image',
        'color',
        'url',
        'url_2',
        'cta_text',
        'cta_text_2',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getColorAttribute($value): ?string
    {
        if ($value && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value)) {
            return $value;
        }

        return null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopePosition(Builder $query, string $position): Builder
    {
        return $query->where('position', $position);
    }

    protected function getIdPrefix(): string
    {
        return 'ban';
    }
}
