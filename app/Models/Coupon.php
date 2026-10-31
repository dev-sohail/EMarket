<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Coupon extends PrefixedModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'coupons';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'type',
        'value',
        'percentage',
        'minimum_order',
        'maximum_discount',
        'usage_limit',
        'per_customer_limit',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $attributes = [
        'used_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'percentage' => 'decimal:2',
            'minimum_order' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'cup';
    }

    /**
     * Give one redemption back to the coupon, floored at zero.
     *
     * The read-modify-write runs under a row lock so two concurrent releases
     * cannot both read the same value, and the floor stops a release for an
     * order that never consumed the coupon from driving the counter negative.
     * A negative `used_count` reads as "below the limit" to every gate
     * (CouponService::validate/apply), which would let the coupon be redeemed
     * past `usage_limit` indefinitely.
     */
    public function decrementUsedCount(): bool
    {
        return DB::transaction(function () {
            $coupon = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();

            if (! $coupon) {
                return false;
            }

            $this->writeUsedCount($coupon, max(0, (int) $coupon->used_count - 1));

            return true;
        });
    }

    /**
     * Release a coupon redemption, idempotently.
     *
     * The `coupon_usages` row is the proof that a redemption was ever counted,
     * so `used_count` is only decremented when a row is actually removed.
     * Releasing twice for the same order — a double-clicked cancel, a retried
     * request, a webhook plus a manual cancellation — is therefore a no-op the
     * second time and can never take the counter below zero.
     */
    public function releaseUsage(?string $orderId = null): void
    {
        DB::transaction(function () use ($orderId) {
            $coupon = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();

            if (! $coupon) {
                return;
            }

            $released = $orderId !== null
                ? $coupon->usages()->where('order_id', $orderId)->delete()
                : $coupon->usages()->delete();

            if ($released === 0) {
                return;
            }

            $this->writeUsedCount($coupon, max(0, (int) $coupon->used_count - $released));
        });
    }

    /**
     * Persist a clamped `used_count` and keep this instance in step with it.
     */
    protected function writeUsedCount(Coupon $coupon, int $usedCount): void
    {
        $coupon->forceFill(['used_count' => $usedCount])->save();

        $this->setAttribute('used_count', $usedCount);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products', 'coupon_id', 'product_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_categories', 'coupon_id', 'category_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $coupon): void {
            // Delete coupon usages individually to fire any model events
            foreach ($coupon->usages()->get() as $usage) {
                $usage->delete();
            }

            // Detach all pivot records to avoid orphaned entries
            $coupon->products()->detach();
            $coupon->categories()->detach();
        });
    }
}
