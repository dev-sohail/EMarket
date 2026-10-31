<?php

namespace App\Models;

use App\Services\Pricing\PricingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends PrefixedModel
{
    use HasFactory;

    protected $table = 'carts';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'session_id',
        'coupon_code',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'string',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'crt';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * The merchandise total, priced from the live catalogue.
     *
     * Read through `PricingService::calculateCartTotal()`, the single authority
     * for cart money, rather than by summing the denormalised
     * `cart_items.unit_price`. That column is a snapshot written at add-to-cart
     * time, so a cart whose prices, sale or campaign moved since then reported a
     * subtotal the order is not going to be charged — and, because
     * `CouponService` measures a coupon's `minimum_order` against this figure,
     * let a customer qualify for a coupon on numbers that no longer held.
     *
     * The service is resolved from the container rather than injected because
     * this is an accessor on a model: `PricingService` depends on `Cart`, so a
     * constructor dependency here would be circular. Callers that already hold
     * a priced cart should read that array's `subtotal` instead of re-running
     * this.
     */
    public function getSubtotalAttribute(): float
    {
        return (float) app(PricingService::class)->calculateCartTotal($this)['subtotal'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $cart): void {
            // Delete cart items individually to fire any model events
            foreach ($cart->items()->get() as $item) {
                $item->delete();
            }
        });
    }
}
