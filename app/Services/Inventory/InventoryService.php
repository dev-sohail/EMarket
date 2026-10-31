<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        protected Inventory $inventory,
        protected InventoryMovement $movement
    ) {}

    public function getStock(?string $productId, ?string $variantId): Inventory
    {
        try {
            return $this->inventory->lockForUpdate()
                ->firstOrCreate(
                    [
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                    ],
                    [
                        'quantity' => $this->resolveInitialQuantity($variantId, $productId),
                        'reserved_quantity' => 0,
                    ]
                );
        } catch (UniqueConstraintViolationException $e) {
            $query = $this->inventory->where('product_id', $productId)
                ->where(function ($query) use ($variantId) {
                    if ($variantId) {
                        $query->where('variant_id', $variantId);
                    } else {
                        $query->whereNull('variant_id');
                    }
                });

            return $query->lockForUpdate()->firstOrFail();
        }
    }

    public function getAvailableStock(?string $productId, ?string $variantId): int
    {
        $inventory = $this->inventory
            ->where('product_id', $productId)
            ->where(function ($query) use ($variantId) {
                if ($variantId) {
                    $query->where('variant_id', $variantId);
                } else {
                    $query->whereNull('variant_id');
                }
            })
            ->first();

        if (! $inventory) {
            // Don't create inventory record on read - return 0 available stock
            return 0;
        }

        return $inventory->quantity - $inventory->reserved_quantity;
    }

    /**
     * Deduct sold units from stock.
     *
     * `$reservedByCaller` is how many of the units the buyer already holds as a
     * cart reservation (a cart line reserves its quantity when it is added).
     * Those units are counted in `reserved_quantity`, so they must not be
     * counted against the buyer who holds them, and they are the only
     * reservation this sale consumes. Without this the last reserved unit could
     * never be bought, and a sale released other shoppers' reservations.
     */
    public function deductStock(
        string $productId,
        ?string $variantId,
        int $quantity,
        ?string $orderId = null,
        int $reservedByCaller = 0
    ): Inventory {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $orderId, $reservedByCaller) {
            $inventory = $this->getStock($productId, $variantId);

            $consumedReservation = max(0, min($reservedByCaller, $quantity, $inventory->reserved_quantity));
            $available = $inventory->quantity - ($inventory->reserved_quantity - $consumedReservation);

            if ($available < $quantity) {
                throw new \InvalidArgumentException("Insufficient stock. Available: {$available}, Requested: {$quantity}");
            }

            $inventory->decrement('quantity', $quantity);

            if ($consumedReservation > 0) {
                $inventory->decrement('reserved_quantity', $consumedReservation);
            }

            $this->logMovement(
                InventoryMovementType::Sale,
                $productId,
                $variantId,
                -$quantity,
                $orderId,
                "Sale - Order #{$orderId}",
                $inventory->getKey()
            );

            $this->syncVariantStockStatus($variantId, $inventory);

            return $inventory->fresh();
        });
    }

    public function reserveStock(
        string $productId,
        ?string $variantId,
        int $quantity,
        ?string $cartItemId = null
    ): Inventory {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $cartItemId) {
            $inventory = $this->getStock($productId, $variantId);

            $available = $inventory->quantity - $inventory->reserved_quantity;

            if ($available < $quantity) {
                throw new \InvalidArgumentException("Insufficient stock. Available: {$available}, Requested: {$quantity}");
            }

            $inventory->increment('reserved_quantity', $quantity);

            $this->logMovement(
                InventoryMovementType::Reserved,
                $productId,
                $variantId,
                $quantity,
                null,
                "Stock reserved - Cart item #{$cartItemId}",
                $inventory->getKey()
            );

            $this->syncVariantStockStatus($variantId, $inventory);

            return $inventory->fresh();
        });
    }

    public function releaseStock(
        string $productId,
        ?string $variantId,
        int $quantity,
        ?string $cartItemId = null
    ): Inventory {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $cartItemId) {
            $inventory = $this->getStock($productId, $variantId);

            $released = min($quantity, $inventory->reserved_quantity);
            if ($released > 0) {
                $inventory->decrement('reserved_quantity', $released);

                $this->logMovement(
                    InventoryMovementType::Released,
                    $productId,
                    $variantId,
                    $released,
                    null,
                    "Reservation released - Cart item #{$cartItemId}",
                    $inventory->getKey()
                );
            }

            return $inventory->fresh();
        });
    }

    public function restoreStock(
        string $productId,
        ?string $variantId,
        int $quantity,
        ?string $orderId = null
    ): Inventory {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $orderId) {
            $inventory = $this->getStock($productId, $variantId);
            $inventory->increment('quantity', $quantity);

            $this->logMovement(
                InventoryMovementType::Return,
                $productId,
                $variantId,
                $quantity,
                $orderId,
                "Stock restored - Order #{$orderId} cancelled/returned",
                $inventory->getKey()
            );

            $this->syncVariantStockStatus($variantId, $inventory);

            return $inventory->fresh();
        });
    }

    public function adjustStock(
        string $productId,
        ?string $variantId,
        int $quantity,
        ?string $note = null
    ): Inventory {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $note) {
            $inventory = $this->getStock($productId, $variantId);

            if ($quantity > 0) {
                $inventory->increment('quantity', $quantity);
            } else {
                $newQuantity = $inventory->quantity - abs($quantity);
                if ($newQuantity < 0) {
                    throw new \InvalidArgumentException("Adjustment would result in negative stock. Current: {$inventory->quantity}, Requested decrement: ".abs($quantity));
                }
                $inventory->decrement('quantity', abs($quantity));
            }

            $this->logMovement(
                InventoryMovementType::Adjustment,
                $productId,
                $variantId,
                $quantity,
                null,
                $note ?? 'Manual stock adjustment',
                $inventory->getKey()
            );

            $this->syncVariantStockStatus($variantId, $inventory);

            return $inventory->fresh();
        });
    }

    public function getLowStock()
    {
        // An unset threshold means "no low-stock reporting", the same contract
        // getLowStockCount() and getLowStockProducts() follow. Falling back to a
        // default here reported a five-unit list on stores that never
        // configured one, which is exactly the noise the other two avoid.
        $threshold = low_stock_threshold();

        if ($threshold === null) {
            return collect();
        }

        return Inventory::with(['product', 'variant'])
            ->where('quantity', '<=', $threshold)
            ->where('quantity', '>', 0)
            ->get();
    }

    public function getLowStockCount(): int
    {
        $threshold = low_stock_threshold();

        if ($threshold === null) {
            return 0;
        }

        return Inventory::where('quantity', '<=', $threshold)
            ->where('quantity', '>', 0)
            ->count();
    }

    public function getLowStockProducts(int $limit = 5)
    {
        $threshold = low_stock_threshold();

        if ($threshold === null) {
            return collect();
        }

        return Inventory::with(['product', 'variant'])
            ->where('quantity', '<=', $threshold)
            ->where('quantity', '>', 0)
            ->orderBy('quantity', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getMovements(string $productId, ?string $variantId = null)
    {
        $inventoryIds = $this->inventory
            ->where('product_id', $productId)
            ->where(function ($query) use ($variantId) {
                if ($variantId) {
                    $query->where('variant_id', $variantId);
                } else {
                    $query->whereNull('variant_id');
                }
            })
            ->pluck('_id');

        return InventoryMovement::with('inventory')
            ->whereIn('inventory_id', $inventoryIds)
            ->latest()
            ->get();
    }

    protected function logMovement(
        InventoryMovementType $type,
        string $productId,
        ?string $variantId,
        int $quantity,
        ?string $orderId,
        ?string $note,
        ?string $inventoryId = null
    ): InventoryMovement {
        // If inventoryId not provided, get it without locking to avoid deadlocks
        if (! $inventoryId) {
            $inventoryId = $this->inventory
                ->where('product_id', $productId)
                ->where(function ($query) use ($variantId) {
                    if ($variantId) {
                        $query->where('variant_id', $variantId);
                    } else {
                        $query->whereNull('variant_id');
                    }
                })
                ->value('_id');
        }

        return $this->movement->create([
            'inventory_id' => $inventoryId,
            'type' => $type->value,
            'quantity' => $quantity,
            'reference_type' => $orderId ? Order::class : null,
            'reference_id' => $orderId,
            'performed_by' => acting_user_id(),
            'notes' => $note,
        ]);
    }

    protected function syncVariantStockStatus(?string $variantId, Inventory $inventory): void
    {
        $available = $inventory->quantity - $inventory->reserved_quantity;

        if ($variantId) {
            ProductVariant::where('_id', $variantId)->update([
                'stock_quantity' => $inventory->quantity,
                'stock_status' => $available > 0 ? 'in_stock' : 'out_of_stock',
            ]);

            return;
        }

        if ($inventory->product_id) {
            Product::where('_id', $inventory->product_id)->update([
                'stock_quantity' => $inventory->quantity,
                'stock_status' => $available > 0 ? 'in_stock' : 'out_of_stock',
            ]);
        }
    }

    protected function resolveInitialQuantity(?string $variantId, ?string $productId = null): int
    {
        if (! $variantId) {
            return $productId ? (int) Product::where('_id', $productId)->value('stock_quantity') : 0;
        }

        $variant = ProductVariant::where('_id', $variantId)
            ->whereNull('deleted_at')
            ->first();

        return $variant ? (int) $variant->stock_quantity : 0;
    }
}
