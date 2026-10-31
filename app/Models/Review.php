<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'reviews';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'product_id',
        'order_id',
        'rating',
        'title',
        'comment',
        'status',
        'is_verified_purchase',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'string',
            'product_id' => 'string',
            'order_id' => 'string',
            'rating' => 'integer',
            'is_verified_purchase' => 'boolean',
            'status' => ReviewStatus::class,
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Approved->value);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReviewImage::class);
    }

    protected function getIdPrefix(): string
    {
        return 'rev';
    }

    protected static function booted(): void
    {
        static::deleting(function (self $review): void {
            // Delete review images individually to fire any model events
            foreach ($review->images()->get() as $image) {
                $image->delete();
            }
        });
    }
}
