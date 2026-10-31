<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wishlist extends PrefixedModel
{
    use HasFactory;

    protected $table = 'wishlists';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'customer_id',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'wsh';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $wishlist): void {
            // Delete wishlist items individually to fire any model events
            foreach ($wishlist->items()->get() as $item) {
                $item->delete();
            }
        });
    }
}
