<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Popup extends PrefixedModel
{
    use HasFactory;

    protected $table = 'popups';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'title',
        'content',
        'type',
        'position',
        'size',
        'subtitle',
        'description',
        'color',
        'overlay_color',
        'image',
        'custom_html',
        'url',
        'cta_text',
        'is_active',
        'starts_at',
        'ends_at',
        'show_on_mobile',
        'show_on_desktop',
        'sort_order',
        'delay_seconds',
        'show_close_button',
        'enable_cookie',
        'cookie_expiry_days',
        'show_only_once',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'show_on_mobile' => 'boolean',
            'show_on_desktop' => 'boolean',
            'sort_order' => 'integer',
            'delay_seconds' => 'integer',
            'show_close_button' => 'boolean',
            'enable_cookie' => 'boolean',
            'cookie_expiry_days' => 'integer',
            'show_only_once' => 'boolean',
        ];
    }

    public function getColorAttribute($value): ?string
    {
        if ($value && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value)) {
            return $value;
        }

        return null;
    }

    public function getOverlayColorAttribute($value): ?string
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

    protected function getIdPrefix(): string
    {
        return 'pop';
    }
}
