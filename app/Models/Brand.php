<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'brands';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'website',
        'description',
        'is_active',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Brand $brand) {
            $brand->products()->detach();
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    protected function getIdPrefix(): string
    {
        return 'brd';
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_brands', 'brand_id', 'product_id');
    }
}
