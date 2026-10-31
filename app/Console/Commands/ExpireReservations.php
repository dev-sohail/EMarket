<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\Inventory\InventoryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;

#[Signature('app:expire-reservations')]
#[Description('Release stock reservations for abandoned cart items and clean up empty carts')]
class ExpireReservations extends Command implements Isolatable
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $ttlMinutes = reservation_ttl_minutes();

        if ($ttlMinutes === null) {
            // `(int) config(…, 30)` returned 0 here, because the env var ships
            // blank and the config default never applies — a zero TTL made the
            // threshold `now()`, so the hourly run released every reservation in
            // the shop, including carts being filled out at that moment.
            $this->warn('INVENTORY_RESERVATION_TTL_MINUTES is not set, so no reservations were expired. Set it in .env to enable this job.');

            return self::SUCCESS;
        }

        $threshold = now()->subMinutes($ttlMinutes);

        $releasedCount = 0;

        DB::transaction(function () use ($threshold, &$releasedCount) {
            $abandonedItems = CartItem::where('updated_at', '<', $threshold)
                ->lockForUpdate()
                ->with('cart')
                ->get();

            foreach ($abandonedItems as $item) {
                // If the cart has already been converted into an order, checkout
                // owns the reservation now — never release stock out from under it.
                if (Order::where('cart_id', $item->cart_id)->exists()) {
                    continue;
                }

                $this->inventoryService->releaseStock(
                    $item->product_id,
                    $item->variant_id,
                    $item->quantity,
                    $item->getKey()
                );

                $item->delete();
                $releasedCount++;
            }

            Cart::doesntHave('items')->where('created_at', '<', $threshold)->delete();
        });

        $this->info("Released {$releasedCount} abandoned reservation(s).");

        return self::SUCCESS;
    }
}
