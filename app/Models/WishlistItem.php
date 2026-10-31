<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends PrefixedModel
{
    use HasFactory;

    protected $table = 'wishlist_items';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'wishlist_id',
        'product_id',
    ];

    protected function casts(): array
    {
        return [
            'wishlist_id' => 'string',
            'product_id' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'wsi';
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
