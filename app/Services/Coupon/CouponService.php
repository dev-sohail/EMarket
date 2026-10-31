<?php

namespace App\Services\Coupon;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use App\Services\Pricing\PricingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function __construct(
        protected Coupon $coupon,
        protected CouponUsage $usage,
        protected PricingService $pricingService
    ) {}

    public function validate(string $code, Cart $cart, ?string $customerId = null): array
    {
        $coupon = $this->getByCode($code);

        if (! $coupon) {
            throw new \InvalidArgumentException('Invalid coupon code.');
        }

        if (! $coupon->is_active) {
            throw new \InvalidArgumentException('This coupon is no longer active.');
        }

        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            throw new \InvalidArgumentException('This coupon is not yet active.');
        }

        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            throw new \InvalidArgumentException('This coupon has expired.');
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            throw new \InvalidArgumentException('This coupon has reached its usage limit.');
        }

        if ($coupon->per_customer_limit) {
            $effectiveCustomerId = $customerId ?? $cart->customer_id;
            $userUsage = $this->usage->where('coupon_id', $coupon->getKey())
                ->where('customer_id', $effectiveCustomerId)
                ->whereNotNull('order_id')
                ->count();

            if ($userUsage >= $coupon->per_customer_limit) {
                throw new \InvalidArgumentException('You have already used this coupon.');
            }
        }

        $subtotal = $this->cartSubtotal($cart);

        if ($coupon->minimum_order && $subtotal < (float) $coupon->minimum_order) {
            throw new \InvalidArgumentException("Minimum order amount is {$coupon->minimum_order}.");
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'message' => 'Coupon is valid.',
        ];
    }

    public function apply(string $code, Cart $cart, ?string $customerId = null): array
    {
        $validation = $this->validate($code, $cart, $customerId);

        $coupon = $validation['coupon'];
        $coupon->load('products', 'categories');

        $pricing = $this->pricingService->calculateCartTotal($cart);
        $subtotal = (float) $pricing['subtotal'];

        if ($coupon->products->isNotEmpty() || $coupon->categories->isNotEmpty()) {
            $linesByItemId = collect($pricing['items'])->keyBy('cart_item_id');
            $eligibleSubtotal = 0.0;

            foreach ($cart->items as $item) {
                $product = $item->product;
                $eligible = false;

                if ($coupon->products->isNotEmpty()
                    && $product
                    && $coupon->products->contains('_id', $product->getKey())) {
                    $eligible = true;
                }

                if (! $eligible
                    && $coupon->categories->isNotEmpty()
                    && $product
                    && $product->categories->pluck('_id')->intersect($coupon->categories->pluck('_id'))->isNotEmpty()) {
                    $eligible = true;
                }

                if ($eligible) {
                    $eligibleSubtotal += (float) ($linesByItemId->get((string) $item->getKey())['line_total'] ?? 0.0);
                }
            }

            if ($eligibleSubtotal <= 0) {
                throw new \InvalidArgumentException('This coupon does not apply to any items in your cart.');
            }

            $subtotal = $eligibleSubtotal;
        }

        $applied = $this->pricingService->applyCoupon($subtotal, $coupon);

        return [
            'discount' => $applied['discount'],
            'free_shipping' => $applied['free_shipping'],
            'coupon' => $coupon,
        ];
    }

    /**
     * The cart subtotal a coupon is measured against.
     *
     * Read from `PricingService::calculateCartTotal()`, the single authority
     * for cart money, rather than by summing `cart_items.unit_price`. That
     * column is a snapshot taken when the line was added, so a cart priced
     * before a price change — or carrying a variant whose `sale_price` or
     * `price_add_to_main` the snapshot never saw — was checked against, and had
     * its discount computed from, figures the order is not actually going to
     * be charged. The snapshot is never a money source here.
     */
    protected function cartSubtotal(Cart $cart): float
    {
        return (float) $this->pricingService->calculateCartTotal($cart)['subtotal'];
    }

    /**
     * Count one redemption, always backed by a `coupon_usages` row.
     *
     * `coupon_usages.customer_id` is nullable, so a guest order is recorded
     * with a null customer and the row is written all the same. That is the
     * point: `Coupon::releaseUsage()` releases a redemption by deleting the
     * usage rows for an order and only decrementing `used_count` when a row was
     * actually removed. A guest redemption that skipped the insert incremented
     * the counter with nothing to justify it, and cancelling that order removed
     * zero rows and decremented zero, so `used_count` drifted upward for the
     * life of the coupon — the coupon exhausted early and never recovered.
     *
     * `coupon_usages.order_id` is `NOT NULL`, so a redemption with no order has
     * nothing attributable to record. The counter is then left alone rather than
     * incremented on evidence that can never be handed back. `CheckoutService`
     * is the only caller and always passes a persisted order.
     *
     * The row is written before the counter moves: both happen inside one
     * transaction, so a failed insert rolls the increment back with it.
     */
    public function recordUsage(Coupon $coupon, ?string $customerId, ?string $orderId): void
    {
        if ($orderId === null || $orderId === '') {
            return;
        }

        DB::transaction(function () use ($coupon, $customerId, $orderId) {
            $locked = $this->coupon->where('_id', $coupon->getKey())->lockForUpdate()->first();

            if (! $locked) {
                return;
            }

            if ($locked->usage_limit && $locked->used_count >= $locked->usage_limit) {
                throw new \InvalidArgumentException('Coupon usage limit has already been reached.');
            }

            $this->usage->create([
                'coupon_id' => $locked->getKey(),
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'used_at' => now(),
            ]);

            $locked->increment('used_count');
        });
    }

    public function getByCode(string $code): ?Coupon
    {
        return $this->coupon->where('code', strtoupper(trim($code)))->first();
    }

    public function create(array $data): Coupon
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = strtoupper($data['code']);
            $data['starts_at'] = $this->toStoreUtc($data['starts_at'] ?? null);
            $data['expires_at'] = $this->toStoreUtc($data['expires_at'] ?? null);

            $coupon = $this->coupon->create($data);

            if (! empty($data['product_ids'])) {
                $coupon->products()->sync($data['product_ids']);
            }

            if (! empty($data['category_ids'])) {
                $coupon->categories()->sync($data['category_ids']);
            }

            return $coupon->load(['products', 'categories']);
        });
    }

    public function update(string $id, array $data): Coupon
    {
        return DB::transaction(function () use ($id, $data) {
            $coupon = $this->coupon->findOrFail($id);

            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }

            $data['starts_at'] = array_key_exists('starts_at', $data)
                ? $this->toStoreUtc($data['starts_at'])
                : $coupon->starts_at;
            $data['expires_at'] = array_key_exists('expires_at', $data)
                ? $this->toStoreUtc($data['expires_at'])
                : $coupon->expires_at;

            $coupon->update($data);

            if (array_key_exists('product_ids', $data)) {
                $coupon->products()->sync($data['product_ids'] ?? []);
            }

            if (array_key_exists('category_ids', $data)) {
                $coupon->categories()->sync($data['category_ids'] ?? []);
            }

            return $coupon->load(['products', 'categories']);
        });
    }

    /**
     * The storefront operates in store timezone. datetime-local inputs
     * carry no timezone, so we interpret them as store-local time and store
     * as-is. Comparisons against now() use the app timezone.
     */
    protected function toStoreUtc($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $storeTimezone = Cache::remember('app_timezone', 3600, fn () => setting('timezone', config('app.timezone')));

        return Carbon::parse($value, $storeTimezone)->format('Y-m-d H:i:s');
    }

    /**
     * Parse a datetime stored in store timezone and convert to app timezone.
     * Returns the datetime in app timezone (formatted as Y-m-d H:i:s).
     */
    public function toAppTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $storeTimezone = Cache::remember('app_timezone', 3600, fn () => setting('timezone', config('app.timezone')));
        $appTimezone = config('app.timezone');

        return Carbon::parse($value, $storeTimezone)
            ->setTimezone($appTimezone)
            ->format('Y-m-d H:i:s');
    }

    /**
     * @deprecated Use toAppTime() instead.
     */
    public function toAppTimeOrOld($value): ?string
    {
        return $this->toAppTime($value);
    }

    public function delete(string $id): bool
    {
        return $this->coupon->findOrFail($id)->delete();
    }

    public function getActive(): Collection
    {
        return $this->coupon->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->get();
    }

    public function getAllCoupons(): Collection
    {
        return Coupon::with(['products', 'categories'])->latest()->get();
    }

    public function getProductsForSelect()
    {
        return Product::orderBy('name')->get();
    }

    public function getCategoriesForSelect()
    {
        return Category::active()->orderBy('name')->get();
    }

    public function toggleStatus(Coupon $coupon): Coupon
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return $coupon->fresh();
    }
}
