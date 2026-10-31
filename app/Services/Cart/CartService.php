<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use App\Services\Pricing\PricingService;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        protected Cart $cart,
        protected CartItem $cartItem,
        protected InventoryService $inventoryService,
        protected PricingService $pricingService
    ) {}

    public function getCart(?string $customerId, ?string $sessionId): ?Cart
    {
        // With neither identity the query would have no constraint and
        // return whichever cart happens to be first, i.e. someone else's.
        if (! $customerId && ! $sessionId) {
            return null;
        }

        $query = Cart::with(['items.product.images', 'items.variant.product.images', 'items.variant.images']);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } else {
            $query->where('session_id', $sessionId);
        }

        $cart = $query->first();

        if ($cart) {
            $this->refreshCartPrices($cart);
        }

        return $cart;
    }

    public function addItem(string $cartId, string $productId, ?string $variantId, int $quantity): CartItem
    {
        $product = Product::where('is_active', true)->where('_id', $productId)->firstOrFail();

        if ($variantId) {
            $variant = ProductVariant::where('_id', $variantId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->with('product')
                ->firstOrFail();
            abort_if($variant->product_id !== $productId, 403);
        }

        $price = $this->resolveCurrentPrice($product, $variant ?? null);

        return DB::transaction(function () use ($cartId, $productId, $variantId, $quantity, $price) {
            $this->inventoryService->reserveStock($productId, $variantId, $quantity);

            $existingItem = $this->cartItem->where('cart_id', $cartId)
                ->where('product_id', $productId)
                ->where(function ($q) use ($variantId) {
                    if ($variantId === null) {
                        $q->whereNull('variant_id');
                    } else {
                        $q->where('variant_id', $variantId);
                    }
                })
                ->first();

            if ($existingItem) {
                $existingItem->update(['quantity' => $existingItem->quantity + $quantity]);

                return $existingItem;
            }

            return $this->cartItem->create([
                'cart_id' => $cartId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $price,
            ]);
        });
    }

    /**
     * The live payable price for a product / variant pair.
     *
     * Delegates to `PricingService` so the price written to `cart_items` at
     * add-to-cart time and the price charged at checkout are produced by the
     * same rule — including `ProductVariant::final_price`, which honours
     * `sale_price` and `price_add_to_main`. Reading the raw `$variant->price`
     * here is what charged the undiscounted amount for a variant on sale.
     */
    protected function resolveCurrentPrice(Product $product, ?ProductVariant $variant): float
    {
        return $this->pricingService->resolveUnitPrice($product, $variant);
    }

    /**
     * Bring `cart_items.unit_price` back in line with the live catalogue.
     *
     * The column is a denormalised copy of the price at add-to-cart time.
     * Checkout now prices the cart from the live product and variant, so a
     * snapshot left behind by a price change, a sale or a campaign would make
     * the per-line figure the cart and checkout pages render disagree with the
     * total that is charged, and would make `CouponService`'s minimum-order
     * check run against a stale subtotal and admit a coupon the order no
     * longer qualifies for. Only rows whose price actually moved are written,
     * and a line that can no longer be sold is left untouched so the cart page
     * can still report it.
     */
    protected function refreshCartPrices(Cart $cart): void
    {
        $lines = $this->pricingService->calculateCartTotal($cart);
        $itemsById = $cart->items->keyBy(fn (CartItem $item) => (string) $item->getKey());

        foreach ($lines['items'] as $line) {
            $item = $itemsById->get($line['cart_item_id']);

            if (! $item || ! $line['purchasable']) {
                continue;
            }

            if ((float) $item->unit_price === (float) $line['unit_price']) {
                continue;
            }

            $item->update(['unit_price' => $line['unit_price']]);
        }
    }

    public function updateItem(string $itemId, int $quantity, ?string $customerId = null, ?string $sessionId = null): CartItem
    {
        return DB::transaction(function () use ($itemId, $quantity, $customerId, $sessionId) {
            $item = $this->cartItem->with(['product.images', 'variant.product.images', 'variant.images'])->lockForUpdate()->findOrFail($itemId);
            $this->ensureOwnsItem($item, $customerId, $sessionId);

            // Validate product/variant is still active
            if ($item->variant_id) {
                if (! $item->variant || $item->variant->trashed() || ! $item->variant->is_active) {
                    throw new \InvalidArgumentException('This product variant is no longer available.');
                }
            }

            if (! $item->product || $item->product->trashed() || ! $item->product->is_active) {
                throw new \InvalidArgumentException('This product is no longer available.');
            }

            if ($quantity <= 0) {
                $this->inventoryService->releaseStock($item->product_id, $item->variant_id, $item->quantity, $item->getKey());
                $item->delete();

                return $item->load(['product', 'variant']);
            }

            $oldQuantity = $item->quantity;
            $delta = $quantity - $oldQuantity;

            if ($delta > 0) {
                $this->inventoryService->reserveStock($item->product_id, $item->variant_id, $delta, $item->getKey());
            } elseif ($delta < 0) {
                $this->inventoryService->releaseStock($item->product_id, $item->variant_id, -$delta, $item->getKey());
            }

            $currentPrice = $this->resolveCurrentPrice($item->product, $item->variant);

            $item->update(['quantity' => $quantity, 'unit_price' => $currentPrice]);

            return $item->load(['product', 'variant']);
        });
    }

    public function removeItem(string $itemId, ?string $customerId = null, ?string $sessionId = null): bool
    {
        return DB::transaction(function () use ($itemId, $customerId, $sessionId) {
            $item = $this->cartItem->lockForUpdate()->findOrFail($itemId);
            $this->ensureOwnsItem($item, $customerId, $sessionId);

            $this->inventoryService->releaseStock($item->product_id, $item->variant_id, $item->quantity, $item->getKey());

            return $item->delete();
        });
    }

    protected function ensureOwnsItem(CartItem $item, ?string $customerId, ?string $sessionId): void
    {
        $cart = $item->cart;

        if ($customerId !== null) {
            abort_if($cart?->customer_id !== $customerId, 403);

            return;
        }

        // No identity at all must not mean "no check": refuse.
        abort_if($sessionId === null || $cart?->session_id !== $sessionId, 403);
    }

    public function clearCart(string $cartId): bool
    {
        return DB::transaction(function () use ($cartId) {
            $items = $this->cartItem->where('cart_id', $cartId)->lockForUpdate()->get();

            foreach ($items as $item) {
                $this->inventoryService->releaseStock($item->product_id, $item->variant_id, $item->quantity, $item->getKey());
            }

            return $this->cartItem->where('cart_id', $cartId)->delete();
        });
    }

    public function getCartTotal(string $cartId): array
    {
        $cart = Cart::with(['items.product.images', 'items.variant.product.images', 'items.variant.images'])->findOrFail($cartId);

        $this->refreshCartPrices($cart);

        $pricing = $this->pricingService->calculateCartTotal($cart);

        return [
            'subtotal' => $pricing['subtotal'],
            'total_discount' => $pricing['total_discount'],
            'total_items' => $cart->items->sum('quantity'),
            'items_count' => $cart->items->count(),
        ];
    }

    public function mergeSessionCart(string $sessionId, string $customerId): Cart
    {
        return DB::transaction(function () use ($sessionId, $customerId) {
            $sessionCart = Cart::where('session_id', $sessionId)->first();
            $customerCart = Cart::where('customer_id', $customerId)->first();

            if (! $customerCart) {
                $customerCart = Cart::create(['customer_id' => $customerId]);
            }

            if ($sessionCart) {
                $customerItems = $customerCart->items()->get();
                $existingMap = [];
                foreach ($customerItems as $existingItem) {
                    $key = $existingItem->product_id.'-'.$existingItem->variant_id;
                    $existingMap[$key] = $existingItem;
                }

                foreach ($sessionCart->items as $item) {
                    $key = $item->product_id.'-'.$item->variant_id;
                    $existingItem = $existingMap[$key] ?? null;

                    if ($existingItem) {
                        // Only release the session cart item's stock, not the customer cart item's stock
                        // The customer cart item already has its stock reserved
                        $this->inventoryService->releaseStock($item->product_id, $item->variant_id, $item->quantity, $item->getKey());
                        $newQuantity = $existingItem->quantity + $item->quantity;
                        $existingItem->update(['quantity' => $newQuantity]);
                        // Reserve the additional quantity (delta)
                        $this->inventoryService->reserveStock($item->product_id, $item->variant_id, $item->quantity, $existingItem->getKey());
                    } else {
                        // Move session cart item to customer cart - stock is already reserved for session cart item
                        // Just update the cart_id, no stock change needed
                        $item->update(['cart_id' => $customerCart->getKey()]);
                    }
                }

                $sessionCart->delete();
            }

            return $customerCart->load(['items.product', 'items.variant.product']);
        });
    }
}
