<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRule extends PrefixedModel
{
    use HasFactory;

    protected $table = 'shipping_rules';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'shipping_zone_id',
        'shipping_method_id',
        'price',
        'free_shipping_threshold',
        'min_weight',
        'max_weight',
        'min_order_value',
        'max_order_value',
        'min_quantity',
        'max_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shipping_zone_id' => 'string',
            'shipping_method_id' => 'string',
            'price' => 'decimal:2',
            'free_shipping_threshold' => 'decimal:2',
            'min_weight' => 'decimal:2',
            'max_weight' => 'decimal:2',
            'min_order_value' => 'decimal:2',
            'max_order_value' => 'decimal:2',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'shr';
    }

    /**
     * Resolve the rate this rule charges for an order, or null when the rule
     * does not apply.
     *
     * Semantics, since every bound is a `decimal:2` / `integer` column that
     * Eloquent casts to a string and a PHP truthiness test on them is wrong
     * ("0.00" is truthy):
     *
     *  - a bound is "unset" only when the column is NULL. A stored `0.00` is a
     *    real bound and is compared numerically, so `max_weight = 0.00` matches
     *    only a zero-weight order rather than every order;
     *  - `free_shipping_threshold = 0.00` is what an untouched admin numeric
     *    input persists, and is treated as "no threshold configured". It must
     *    never mean "free on every order", so the threshold only applies when it
     *    is greater than zero;
     *  - `price = 0.00` carries no usable rate and means "this rule does not
     *    apply" (null), not "shipping is free";
     *  - the weight/value/quantity bands are checked first, so a threshold on a
     *    band the order falls outside of does not grant free shipping;
     *  - a NULL `$quantity` means the quantity is unknown, so a rule that
     *    constrains quantity cannot be evaluated and does not apply.
     */
    public function calculateRate(float $weight, float $orderValue, ?int $quantity = null): ?float
    {
        if (! $this->matches($weight, $orderValue, $quantity)) {
            return null;
        }

        $threshold = $this->numericBound('free_shipping_threshold');

        if ($threshold !== null && $threshold > 0 && $orderValue >= $threshold) {
            return 0.0;
        }

        $price = $this->numericBound('price');

        if ($price === null) {
            return null;
        }

        // Fixed: price = 0.00 should return null (rule does not apply), not 0.0 (free shipping)
        if ($price == 0.00) {
            return null;
        }

        return round($price, 2);
    }

    /**
     * Whether this rule's weight, order-value and quantity bands all cover the
     * order. Shared with ShippingService so the bands are evaluated once, in one
     * place, for every caller.
     */
    public function matches(float $weight, float $orderValue, ?int $quantity = null): bool
    {
        $minWeight = $this->numericBound('min_weight');
        $maxWeight = $this->numericBound('max_weight');
        $minOrderValue = $this->numericBound('min_order_value');
        $maxOrderValue = $this->numericBound('max_order_value');
        $minQuantity = $this->numericBound('min_quantity');
        $maxQuantity = $this->numericBound('max_quantity');

        $inWeight = ($minWeight === null || $weight >= $minWeight)
            && ($maxWeight === null || $weight <= $maxWeight);

        $inValue = ($minOrderValue === null || $orderValue >= $minOrderValue)
            && ($maxOrderValue === null || $orderValue <= $maxOrderValue);

        $inQuantity = $quantity === null
            ? ($minQuantity === null && $maxQuantity === null)
            : ($minQuantity === null || $quantity >= $minQuantity)
                && ($maxQuantity === null || $quantity <= $maxQuantity);

        return $inWeight && $inValue && $inQuantity;
    }

    /**
     * Read a numeric column as a float, or null when the column is NULL.
     *
     * The null check has to happen on the raw attribute: a `decimal:2` cast
     * turns NULL into null but turns 0 into the string "0.00", which is
     * truthy and would defeat a `if ($this->column)` style guard.
     */
    protected function numericBound(string $attribute): ?float
    {
        $value = $this->getAttribute($attribute);

        return is_null($value) ? null : (float) $value;
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function method(): BelongsTo
    {
        // The column is shipping_method_id, not the convention's method_id.
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}
