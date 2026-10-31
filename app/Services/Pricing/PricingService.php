<?php

namespace App\Services\Pricing;

use App\Enums\CouponType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use Illuminate\Support\Collection;

class PricingService
{
    /**
     * The `charge_type` a caller uses to express the shipping fee as a line
     * charge. `CheckoutController::process()` always sends one.
     *
     * It restates the fee that is already carried by `orders.shipping_cost`,
     * so it is dropped from the composition and from the persisted charge
     * list: adding it on top of the scalar is the double-shipping defect.
     */
    public const CHARGE_TYPE_SHIPPING = 'shipping';

    /**
     * Convert a money amount to integer cents.
     *
     * Every sum, allocation and comparison in this service runs in cents.
     * Money is stored as `decimal(12,2)` but PHP floats are binary, so
     * accumulating `unit_price * quantity` in float and rounding once at the
     * end is how a cart subtotal drifts away from the sum of its own lines.
     */
    public function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert integer cents back to a 2dp money amount.
     */
    public function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * The payable unit price of a product / variant pair — the single
     * definition used by the cart, the checkout preview and the order.
     *
     * A variant is priced through `ProductVariant::final_price`, so
     * `sale_price` and `price_add_to_main` are honoured. Reading the raw
     * `price` instead — what this service and `CartService` used to do — charged
     * the undiscounted amount for every variant that was on sale. A line
     * without a variant uses `Product::final_price`.
     *
     * Precedence is flash sale, then campaign, then base price. A flash price
     * that is not a positive amount is ignored: `flash_sale_products.flash_price`
     * is `NOT NULL DEFAULT 0.00`, so an unpriced flash sale used to make the
     * line free instead of falling through to the real price.
     */
    public function resolveUnitPrice(Product $product, ?ProductVariant $variant = null): float
    {
        $price = (float) ($variant ? $variant->final_price : $product->final_price);

        $flashPrice = $this->activeFlashPrice($product);

        if ($flashPrice !== null) {
            $price = $flashPrice;
        } else {
            $campaignPrice = $this->activeCampaignPrice($product);

            if ($campaignPrice !== null) {
                $price = $campaignPrice;
            }
        }

        return max(0.0, $this->fromCents($this->toCents($price)));
    }

    /**
     * The "compare at" price of a line, or null when there is nothing to
     * compare against. A zero base price is not a compare-at price, so it
     * collapses to null rather than making every line look discounted.
     */
    public function compareUnitPrice(?Product $product, ?ProductVariant $variant): ?float
    {
        if (! $product) {
            return null;
        }

        $list = (float) ($variant ? $variant->list_price : ($product->base_price ?? 0));
        $list = $this->fromCents($this->toCents($list));

        return $list > 0 ? $list : null;
    }

    /**
     * Whether a cart line can still be sold.
     *
     * `cart_items.product_id` is `NOT NULL` and every product / variant
     * relation is soft-delete scoped, so a line whose product or variant has
     * been removed resolves to null on the relation rather than raising. Those
     * lines are still priced from the stored snapshot so a cart page renders,
     * but the caller blocks on them before an order is created. An inactive
     * (but not deleted) product or variant is equally unsellable.
     */
    public function isPurchasable(?Product $product, ?ProductVariant $variant, ?string $variantId = null): bool
    {
        if (! $product || $product->trashed() || ! $product->is_active) {
            return false;
        }

        if (! $variantId) {
            return true;
        }

        return $variant !== null && ! $variant->trashed() && $variant->is_active;
    }

    /**
     * Price one cart line from the live catalogue.
     *
     * `cart_items.unit_price` is a denormalised snapshot written when the item
     * was added and is never trusted as the money source: a line that entered
     * the cart before a price change, a sale or a campaign started was being
     * charged the old amount. The snapshot is used only for a line that can no
     * longer be sold, so the cart still renders and the caller can report it.
     *
     * @return array{cart_item_id: string, product_id: string, variant_id: string|null, product_name: string, purchasable: bool, unit_price: float, compare_price: float|null, quantity: int, line_total: float, discount: float}
     */
    public function priceCartItem(CartItem $item): array
    {
        $product = $item->product;
        $variant = $item->variant;
        $quantity = max(0, (int) $item->quantity);
        $purchasable = $this->isPurchasable($product, $variant, $item->variant_id);

        $unitPrice = $purchasable
            ? $this->resolveUnitPrice($product, $variant)
            : $this->fromCents($this->toCents((float) $item->unit_price));

        $unitPriceCents = $this->toCents($unitPrice);
        $comparePrice = $purchasable ? $this->compareUnitPrice($product, $variant) : null;
        $compareCents = $comparePrice === null ? $unitPriceCents : $this->toCents($comparePrice);

        return [
            'cart_item_id' => (string) $item->getKey(),
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'product_name' => $product?->name ?? 'Product',
            'purchasable' => $purchasable,
            'unit_price' => $this->fromCents($unitPriceCents),
            'compare_price' => $comparePrice,
            'quantity' => $quantity,
            'line_total' => $this->fromCents($unitPriceCents * $quantity),
            'discount' => $this->fromCents(max(0, $compareCents - $unitPriceCents) * $quantity),
        ];
    }

    /**
     * Price every line of a cart from the live catalogue.
     *
     * This is the one entry point behind the cart summary, the checkout preview
     * and `CheckoutService::createOrder()`, so the figure the customer is
     * shown and the figure that is charged come out of the same code.
     *
     * @return array{items: array<int, array<string, mixed>>, subtotal: float, total_discount: float, unavailable: array<int, string>, has_unavailable: bool}
     */
    public function calculateCartTotal(Cart $cart): array
    {
        $items = $cart->relationLoaded('items')
            ? $cart->items
            : $cart->items()->with(['product', 'variant.product'])->get();

        foreach ($items as $item) {
            $item->loadMissing(['product', 'variant.product']);
        }

        $this->loadActiveSalePricing($items->pluck('product')->filter());

        $lines = [];
        $subtotalCents = 0;
        $discountCents = 0;
        $unavailable = [];

        foreach ($items as $item) {
            $line = $this->priceCartItem($item);

            $subtotalCents += $this->toCents($line['line_total']);
            $discountCents += $this->toCents($line['discount']);
            $lines[] = $line;

            if (! $line['purchasable']) {
                $unavailable[] = $line['product_name'];
            }
        }

        return [
            'items' => $lines,
            'subtotal' => $this->fromCents($subtotalCents),
            'total_discount' => $this->fromCents($discountCents),
            'unavailable' => $unavailable,
            'has_unavailable' => $unavailable !== [],
        ];
    }

    /**
     * Compose the order total. This is the only place the composition is
     * written down, and both the preview and `createOrder()` call it.
     *
     * grand_total = subtotal - discount + shipping + tax + charges
     *
     * Shipping enters exactly once, as the scalar, mirroring
     * `orders.shipping_cost`. Any charge whose `charge_type` is `shipping` is
     * that same fee restated by the caller, so it is excluded from the charge
     * sum and from the returned charge list rather than being added on top.
     *
     * The discount is clamped to the subtotal: a discount larger than the
     * subtotal used to make `subtotal - discount` negative, which the final
     * `max(0, ...)` silently turned into a free order while the persisted
     * discount column still recorded the impossible figure.
     *
     * @param  array<int, array<string, mixed>>  $charges
     * @return array{subtotal: float, discount: float, shipping: float, tax: float, charges: array<int, array<string, mixed>>, total_charges: float, grand_total: float}
     */
    public function calculateOrderTotals(
        float $subtotal,
        float $discount,
        float $shipping,
        float $tax,
        array $charges = []
    ): array {
        $subtotalCents = max(0, $this->toCents($subtotal));
        $discountCents = min(max(0, $this->toCents($discount)), $subtotalCents);
        $shippingCents = max(0, $this->toCents($shipping));
        $taxCents = max(0, $this->toCents($tax));

        $chargeCents = 0;
        $extraCharges = [];

        foreach ($charges as $charge) {
            if (! is_array($charge)) {
                continue;
            }

            $type = strtolower(trim((string) ($charge['charge_type'] ?? '')));

            if ($type === self::CHARGE_TYPE_SHIPPING) {
                continue;
            }

            $amountCents = $this->toCents((float) ($charge['amount'] ?? 0));
            $chargeCents += $amountCents;
            $extraCharges[] = array_merge($charge, ['amount' => $this->fromCents($amountCents)]);
        }

        $grandTotalCents = $subtotalCents - $discountCents + $shippingCents + $taxCents + $chargeCents;

        return [
            'subtotal' => $this->fromCents($subtotalCents),
            'discount' => $this->fromCents($discountCents),
            'shipping' => $this->fromCents($shippingCents),
            'tax' => $this->fromCents($taxCents),
            'charges' => $extraCharges,
            'total_charges' => $this->fromCents($chargeCents),
            'grand_total' => $this->fromCents(max(0, $grandTotalCents)),
        ];
    }

    /**
     * Preview alias of {@see calculateOrderTotals()}. The cart, checkout and
     * AJAX controllers build their displayed figure with this call, so the
     * preview is the same composition the order is written with rather than a
     * look-alike that can drift from it.
     *
     * @param  array<int, array<string, mixed>>  $charges
     * @return array{subtotal: float, discount: float, shipping: float, tax: float, charges: array<int, array<string, mixed>>, total_charges: float, grand_total: float}
     */
    public function calculateGrandTotal(
        float $subtotal,
        float $discount,
        float $shipping,
        float $tax,
        array $charges = []
    ): array {
        return $this->calculateOrderTotals($subtotal, $discount, $shipping, $tax, $charges);
    }

    /**
     * Spread one order-level amount across the order lines.
     *
     * The allocation is proportional to the basis the caller passes and is
     * exact: the last line absorbs the remainder, so the allocations always
     * sum to the amount and never to a figure derived from a different basis.
     * A basis of zero short-circuits instead of dividing by zero, which is what
     * a fully discounted or zero-priced cart used to do. No line can receive
     * more than the amount still to be allocated, so an amount larger than the
     * basis (a flat tax on a small order, say) cannot produce a negative
     * allocation.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, float>
     */
    public function allocateAcrossLines(array $lines, float $amount): array
    {
        $count = count($lines);
        $allocations = array_fill(0, $count, 0.0);
        $totalCents = $this->toCents($amount);

        if ($count === 0 || $totalCents <= 0) {
            return $allocations;
        }

        $basis = array_map(
            fn (array $line) => max(0, $this->toCents((float) ($line['line_total'] ?? 0))),
            array_values($lines)
        );
        $basisCents = array_sum($basis);

        if ($basisCents <= 0) {
            $allocations[0] = $this->fromCents($totalCents);

            return $allocations;
        }

        $running = 0;

        foreach ($basis as $index => $value) {
            if ($index === $count - 1) {
                $share = $totalCents - $running;
            } else {
                $proportional = (int) round($value * $totalCents / $basisCents);
                $remaining = max(0, $totalCents - $running);
                $share = min(max(0, $proportional), $remaining);
            }

            $allocations[$index] = $this->fromCents($share);
            $running += $share;
        }

        return $allocations;
    }

    /**
     * Price a single product / variant pair. Retained as a named entry point
     * for callers that price one line rather than a cart; it now resolves the
     * same live price as `calculateCartTotal()` and can no longer disagree
     * with it.
     *
     * @return array{unit_price: float, compare_price: float|null, quantity: int, line_total: float, discount: float}
     */
    public function calculateItemPrice(?Product $product, ?ProductVariant $variant, int $quantity): array
    {
        $product ??= $variant?->product;
        $quantity = max(0, $quantity);

        $unitPrice = $product ? $this->resolveUnitPrice($product, $variant) : 0.0;
        $comparePrice = $this->compareUnitPrice($product, $variant);
        $unitCents = $this->toCents($unitPrice);
        $compareCents = $comparePrice === null ? $unitCents : $this->toCents($comparePrice);

        return [
            'unit_price' => $this->fromCents($unitCents),
            'compare_price' => $compareCents > $unitCents ? $this->fromCents($compareCents) : null,
            'quantity' => $quantity,
            'line_total' => $this->fromCents($unitCents * $quantity),
            'discount' => $this->fromCents(max(0, $compareCents - $unitCents) * $quantity),
        ];
    }

    /**
     * The net merchandise amount after a discount, never negative.
     */
    public function applyDiscount(float $subtotal, float $discount): float
    {
        $subtotalCents = max(0, $this->toCents($subtotal));
        $discountCents = min(max(0, $this->toCents($discount)), $subtotalCents);

        return $this->fromCents($subtotalCents - $discountCents);
    }

    /**
     * Tax for a net amount, with a per-rule breakdown.
     *
     * @param  array<int, object>  $taxRules
     * @return array{total_tax: float, breakdown: array<int, array{name: mixed, rate: mixed, amount: float}>}
     */
    public function applyTax(float $subtotal, array $taxRules): array
    {
        $subtotalCents = max(0, $this->toCents($subtotal));
        $totalCents = 0;
        $breakdown = [];

        foreach ($taxRules as $rule) {
            $amountCents = ($rule->type ?? null) === 'fixed'
                ? $this->toCents((float) $rule->rate)
                : (int) round($subtotalCents * ((float) $rule->rate / 100));

            $totalCents += $amountCents;
            $breakdown[] = [
                'name' => $rule->name,
                'rate' => $rule->rate,
                'amount' => $this->fromCents($amountCents),
            ];
        }

        return [
            'total_tax' => $this->fromCents($totalCents),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Coupon discount for a subtotal, with the free-shipping flag.
     *
     * @return array{discount: float, free_shipping: bool}
     */
    public function applyCoupon(float $subtotal, Coupon $coupon): array
    {
        $subtotalCents = max(0, $this->toCents($subtotal));
        $type = $coupon->type instanceof CouponType ? $coupon->type->value : $coupon->type;
        $discountCents = 0;

        switch ($type) {
            case CouponType::Percentage->value:
                $discountCents = (int) round($subtotalCents * ((float) $coupon->value / 100));

                // `maximum_discount` is a decimal column: Eloquent hands back the
                // string "0.00" for an unset cap, which is truthy in PHP, so it
                // is compared numerically rather than tested for truth.
                $maximumCents = $this->toCents((float) ($coupon->maximum_discount ?? 0));

                if ($maximumCents > 0 && $discountCents > $maximumCents) {
                    $discountCents = $maximumCents;
                }
                break;

            case CouponType::Fixed->value:
                $discountCents = min($this->toCents((float) $coupon->value), $subtotalCents);
                break;

            case CouponType::FreeShipping->value:
                $discountCents = 0;
                break;
        }

        return [
            'discount' => $this->fromCents(min($discountCents, $subtotalCents)),
            'free_shipping' => $type === CouponType::FreeShipping->value,
        ];
    }

    /**
     * Quote one shipping method for a destination.
     *
     * @return array{method: string, cost: float, estimated_days: int|null}
     */
    public function calculateShipping(
        string $city,
        string $method,
        float $weight,
        float $orderValue,
        int $quantity = 1
    ): array {
        $city = trim((string) preg_replace('/\s+/u', ' ', $city));
        $shippingMethod = $this->resolveShippingMethod($method);

        $rulesQuery = ShippingRule::query()
            ->where('shipping_method_id', $shippingMethod->getKey())
            ->where('is_active', true)
            ->whereHas('shippingZone.cities', fn ($query) => $query->where('city', $city));

        $hasAnyRules = (clone $rulesQuery)->exists();
        $cost = null;

        foreach ((clone $rulesQuery)->orderBy('_id')->get() as $rule) {
            if (! $rule->matches($weight, $orderValue, $quantity)) {
                continue;
            }

            $rate = $rule->calculateRate($weight, $orderValue, $quantity);

            if ($rate !== null) {
                $cost = $rate;

                break;
            }
        }

        if ($cost === null && ! $hasAnyRules) {
            $cost = (float) $shippingMethod->base_cost
                + ($weight * (float) ($shippingMethod->per_kg_cost ?? 0));
        }

        if ($cost === null) {
            throw new \RuntimeException('This shipping method is not available for the given order.');
        }

        return [
            'method' => $shippingMethod->name,
            'cost' => max(0.0, $this->fromCents($this->toCents($cost))),
            'estimated_days' => $shippingMethod->estimated_days ?? null,
        ];
    }

    /**
     * Resolve a shipping method from an `_id` or an exact name; the table has
     * no slug to resolve from.
     */
    protected function resolveShippingMethod(string $method): ShippingMethod
    {
        $method = trim($method);

        $shippingMethod = ShippingMethod::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('_id', $method)->orWhere('name', $method))
            ->first();

        if (! $shippingMethod) {
            throw new \RuntimeException('The selected shipping method is not available.');
        }

        return $shippingMethod;
    }

    /**
     * The active flash-sale price for a product, or null.
     *
     * Uses the already-loaded relation when `calculateCartTotal()` has
     * batch-loaded it, so pricing a cart does not run a query per line.
     */
    protected function activeFlashPrice(Product $product): ?float
    {
        $sales = $product->relationLoaded('flashSales')
            ? $product->getRelation('flashSales')
            : $product->flashSales()
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                // A NULL `ends_at` means the sale never expires, so it must not
                // be filtered out here; the campaign branch handles it the same
                // way and MarketingService::getActiveFlashSales() is the third
                // place this predicate lives.
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->get();

        foreach ($sales as $sale) {
            $price = (float) ($sale->pivot->flash_price ?? $sale->sale_price);

            if ($price > 0) {
                return $this->fromCents($this->toCents($price));
            }
        }

        return null;
    }

    /**
     * The active campaign price for a product, or null. A campaign pivot with
     * no price is skipped rather than repricing the product to zero.
     */
    protected function activeCampaignPrice(Product $product): ?float
    {
        $campaigns = $product->relationLoaded('campaigns')
            ? $product->getRelation('campaigns')
            : $product->campaigns()
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->get();

        foreach ($campaigns as $campaign) {
            $raw = $campaign->pivot->campaign_price ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $price = (float) $raw;

            if ($price > 0) {
                return $this->fromCents($this->toCents($price));
            }
        }

        return null;
    }

    /**
     * Batch-load the two sale-price relations for every product on the cart.
     *
     * Pricing a cart resolves a flash sale and a campaign per line, so without
     * this the cart and checkout pages run two extra queries per line — an N+1
     * added to the money path. The relations are attached to the instances the
     * cart already holds, so `resolveUnitPrice()` never issues a query for them.
     *
     * @param  Collection<int, Product>  $products
     */
    protected function loadActiveSalePricing(Collection $products): void
    {
        $byKey = $products
            ->filter(fn ($product) => $product instanceof Product)
            ->unique(fn (Product $product) => (string) $product->getKey())
            ->keyBy(fn (Product $product) => (string) $product->getKey());

        if ($byKey->isEmpty()) {
            return;
        }

        $now = now();

        Product::with([
            'flashSales' => fn ($query) => $query->where('is_active', true)
                ->where('starts_at', '<=', $now)
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
            'campaigns' => fn ($query) => $query->where('is_active', true)
                ->where('starts_at', '<=', $now)
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
        ])->whereIn('_id', $byKey->keys()->all())
            ->get()
            ->each(function (Product $fresh) use ($byKey) {
                $target = $byKey->get((string) $fresh->getKey());

                if ($target instanceof Product) {
                    $target->setRelation('flashSales', $fresh->getRelation('flashSales'));
                    $target->setRelation('campaigns', $fresh->getRelation('campaigns'));
                }
            });
    }
}
