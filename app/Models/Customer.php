<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = [
        'email',
        'phone',
    ];

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id')->withDefault();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id', '_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id', '_id');
    }

    public function wishlist(): HasOne
    {
        return $this->hasOne(Wishlist::class, 'customer_id', '_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id', '_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ProductReturn::class, 'customer_id', '_id');
    }

    public function affiliate(): HasOne
    {
        return $this->hasOne(Affiliate::class, 'customer_id', '_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    protected function getIdPrefix(): string
    {
        return 'cst';
    }

    protected static function booted(): void
    {
        static::deleting(function (self $customer): void {
            // Delete all related customer records individually to fire any model events
            foreach ($customer->addresses()->get() as $address) {
                $address->delete();
            }

            foreach ($customer->orders()->get() as $order) {
                $order->delete();
            }

            if ($customer->wishlist) {
                $customer->wishlist->delete();
            }

            foreach ($customer->reviews()->get() as $review) {
                $review->delete();
            }

            foreach ($customer->returns()->get() as $return) {
                $return->delete();
            }

            if ($customer->affiliate) {
                $customer->affiliate->delete();
            }
        });
    }
}
