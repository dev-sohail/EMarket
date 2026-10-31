<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IntegrityCheckCommand extends Command
{
    // Every check runs on every invocation. The command used to declare an
    // `--all` switch that handle() never read, so the flag looked like it
    // selected a subset while the full set ran regardless.
    protected $signature = 'app:integrity-check';

    protected $description = 'Run data integrity checks without mutating data';

    public function handle(): int
    {
        $this->info('Running data integrity checks...');
        $this->newLine();

        $this->checkVariantAttributeMapping();
        $this->checkDuplicateSkus();
        $this->checkProductInventoryConsistency();
        $this->checkNegativeStock();
        $this->checkReservedExceedsQuantity();
        $this->checkCartLinesForDeletedProducts();
        $this->checkOrderItemsForDeletedVariants();
        $this->checkInvalidOrderStatusTransitions();
        $this->checkReturnQuantityExceedsOrdered();
        $this->checkReviewsForDeletedProducts();
        $this->checkOrphanedInventoryRows();
        $this->checkOrphanedImages();

        $this->newLine();
        $this->info('Integrity check complete.');

        return Command::SUCCESS;
    }

    protected function checkVariantAttributeMapping(): void
    {
        $this->info('--- Variant ↔ Attribute Mapping ---');

        $variants = ProductVariant::with('attributeValues')->get();
        $issues = 0;

        foreach ($variants as $variant) {
            $attributeIds = $variant->attributeValues->pluck('pivot.attribute_id')->filter()->unique();

            if ($attributeIds->count() !== $variant->attributeValues->count()) {
                $this->warn("  Variant #{$variant->getKey()} ({$variant->name}): attribute values missing attribute_id pivot");
                $issues++;
            }

            $valueAttributeIds = $variant->attributeValues->pluck('attribute_id')->filter();

            foreach ($valueAttributeIds as $idx => $declaredAttrId) {
                $pivotAttrId = $variant->attributeValues->values()->get($idx)?->pivot?->attribute_id;

                if ($pivotAttrId && $pivotAttrId !== $declaredAttrId) {
                    $this->warn("  Variant #{$variant->getKey()}: attribute_value #{$variant->attributeValues->values()->get($idx)->getKey()} declares attribute_id={$declaredAttrId} but pivot has attribute_id={$pivotAttrId}");
                    $issues++;
                }
            }
        }

        $issues === 0
            ? $this->line('  ✓ No variant/attribute mapping issues')
            : $this->warn("  ✗ {$issues} variant/attribute issues found");
    }

    protected function checkDuplicateSkus(): void
    {
        $this->info('--- Duplicate SKUs ---');

        $duplicates = Product::select('sku', DB::raw('count(*) as count'))
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            foreach ($duplicates as $dup) {
                $this->warn("  Duplicate SKU '{$dup->sku}' appears {$dup->count} times");
            }
        } else {
            $this->line('  ✓ No duplicate product SKUs');
        }

        $variantDuplicates = ProductVariant::select('sku', DB::raw('count(*) as count'))
            ->whereNotNull('sku')
            ->groupBy('sku')
            ->having('count', '>', 1)
            ->get();

        if ($variantDuplicates->isNotEmpty()) {
            foreach ($variantDuplicates as $dup) {
                $this->warn("  Duplicate variant SKU '{$dup->sku}' appears {$dup->count} times");
            }
        } else {
            $this->line('  ✓ No duplicate variant SKUs');
        }
    }

    protected function checkProductInventoryConsistency(): void
    {
        $this->info('--- Product ↔ Inventory Consistency ---');

        $variants = ProductVariant::with('inventory')->get();
        $issues = 0;

        foreach ($variants as $variant) {
            $inventoryQty = $variant->inventory->sum('quantity');

            if ($variant->stock_quantity !== $inventoryQty) {
                $this->warn("  Variant #{$variant->getKey()}: stock_quantity={$variant->stock_quantity} but inventories sum={$inventoryQty}");
                $issues++;
            }
        }

        $issues === 0
            ? $this->line('  ✓ All variant/inventory quantities consistent')
            : $this->warn("  ✗ {$issues} variant/inventory mismatches");
    }

    protected function checkNegativeStock(): void
    {
        $this->info('--- Negative Stock ---');

        $negative = Inventory::where('quantity', '<', 0)->get();

        if ($negative->isNotEmpty()) {
            foreach ($negative as $inv) {
                $this->warn("  Inventory #{$inv->getKey()}: quantity={$inv->quantity}");
            }
        } else {
            $this->line('  ✓ No negative inventory quantities');
        }
    }

    protected function checkReservedExceedsQuantity(): void
    {
        $this->info('--- Reserved > Quantity ---');

        $issues = Inventory::whereColumn('reserved_quantity', '>', 'quantity')->get();

        if ($issues->isNotEmpty()) {
            foreach ($issues as $inv) {
                $this->warn("  Inventory #{$inv->getKey()}: reserved={$inv->reserved_quantity} > quantity={$inv->quantity}");
            }
        } else {
            $this->line('  ✓ No reserved_quantity exceeds quantity');
        }
    }

    protected function checkCartLinesForDeletedProducts(): void
    {
        $this->info('--- Cart Lines for Deleted/Inactive Products ---');

        $items = CartItem::with('product')->get();
        $issues = 0;

        foreach ($items as $item) {
            if (! $item->product || ! $item->product->is_active) {
                $this->warn("  Cart item #{$item->getKey()}: references missing/inactive product #{$item->product_id}");
                $issues++;
            }
        }

        $issues === 0
            ? $this->line('  ✓ All cart lines reference active products')
            : $this->warn("  ✗ {$issues} cart line issues");
    }

    protected function checkOrderItemsForDeletedVariants(): void
    {
        $this->info('--- Order Items for Deleted Variants ---');

        $items = OrderItem::whereNotNull('variant_id')->with('variant')->get();
        $issues = 0;

        foreach ($items as $item) {
            if (! $item->variant) {
                $this->warn("  Order item #{$item->getKey()}: references deleted variant #{$item->variant_id}");
                $issues++;
            }
        }

        $issues === 0
            ? $this->line('  ✓ All order item variant references valid')
            : $this->warn("  ✗ {$issues} order item variant issues");
    }

    protected function checkInvalidOrderStatusTransitions(): void
    {
        $this->info('--- Invalid Order Status Transitions ---');

        $histories = OrderStatusHistory::orderBy('order_id')->orderBy('created_at')->get();
        // Every status OrderStatus can write. A hand-kept five-entry list flagged
        // confirmed, ready_to_ship, out_for_delivery, completed and returned
        // history rows as "invalid".
        $validStatuses = array_map(fn (OrderStatus $status) => $status->value, OrderStatus::cases());
        $issues = 0;

        foreach ($histories as $history) {
            $toStatus = $history->to_status instanceof \BackedEnum ? $history->to_status->value : $history->to_status;

            if (! in_array($toStatus, $validStatuses, true)) {
                $this->warn("  Order #{$history->order_id}: invalid status '{$toStatus}' in history");
                $issues++;
            }
        }

        $issues === 0
            ? $this->line('  ✓ All order status transitions valid')
            : $this->warn("  ✗ {$issues} invalid status transitions");
    }

    protected function checkReturnQuantityExceedsOrdered(): void
    {
        $this->info('--- Return Quantity Exceeds Ordered ---');

        $items = ReturnItem::with('orderItem')->get();
        $issues = 0;

        foreach ($items as $item) {
            if ($item->orderItem && $item->quantity > $item->orderItem->quantity) {
                $this->warn("  Return item #{$item->getKey()}: quantity={$item->quantity} > ordered={$item->orderItem->quantity}");
                $issues++;
            }
        }

        $issues === 0
            ? $this->line('  ✓ All return quantities within ordered limits')
            : $this->warn("  ✗ {$issues} return quantity violations");
    }

    protected function checkReviewsForDeletedProducts(): void
    {
        $this->info('--- Reviews for Deleted Products ---');

        $reviews = Review::with('product')->get();
        $issues = 0;

        foreach ($reviews as $review) {
            if (! $review->product) {
                $this->warn("  Review #{$review->getKey()}: references deleted product #{$review->product_id}");
                $issues++;
            }
        }

        $issues === 0
            ? $this->line('  ✓ All review product references valid')
            : $this->warn("  ✗ {$issues} review product issues");
    }

    protected function checkOrphanedInventoryRows(): void
    {
        $this->info('--- Orphaned Inventory Rows ---');

        $orphans = Inventory::whereDoesntHave('product')->count()
            + Inventory::whereNotNull('variant_id')->whereDoesntHave('variant')->count();

        $orphans === 0
            ? $this->line('  ✓ No orphaned inventory rows')
            : $this->warn("  ✗ {$orphans} orphaned inventory rows found");
    }

    protected function checkOrphanedImages(): void
    {
        $this->info('--- Orphaned Images ---');

        $orphans = ProductImage::whereDoesntHave('product')->count()
            + ReviewImage::whereDoesntHave('review')->count();

        $orphans === 0
            ? $this->line('  ✓ No orphaned images')
            : $this->warn("  ✗ {$orphans} orphaned images found");
    }
}
