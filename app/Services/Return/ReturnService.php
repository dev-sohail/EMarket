<?php

namespace App\Services\Return;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ProductReturn as ReturnModel;
use App\Models\ReturnItem;
use App\Models\ReturnStatusHistory;
use App\Services\Inventory\InventoryService;
use App\Services\Order\OrderService;
use App\Services\Payment\RefundService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnService
{
    private const ALLOWED_SORT_COLUMNS = [
        '_id', 'order_id', 'customer_id', 'status', 'created_at', 'updated_at',
    ];

    public function __construct(
        protected ReturnModel $return,
        protected InventoryService $inventoryService,
        protected OrderService $orderService,
        protected ?RefundService $refundService = null
    ) {}

    private const ALLOWED_TRANSITIONS = [
        'requested' => ['under_review', 'approved', 'rejected'],
        'under_review' => ['approved', 'rejected'],
        'approved' => ['returned', 'rejected'],
        'returned' => ['completed'],
        'rejected' => [],
        'completed' => [],
    ];

    /**
     * Order statuses a return can settle.
     *
     * A return is only requestable while the goods are with the customer
     * (shipped or delivered), so completion is the first moment the order can
     * honestly be `returned`. Anything else — still in the warehouse, already
     * cancelled, already returned — is left untouched.
     */
    private const SETTLEABLE_ORDER_STATUSES = [
        OrderStatus::Shipped,
        OrderStatus::OutForDelivery,
        OrderStatus::Delivered,
    ];

    public function request(array $data): ReturnModel
    {
        return DB::transaction(function () use ($data) {
            $order = Order::lockForUpdate()->findOrFail($data['order_id']);

            $existingReturn = $order->returns()
                ->whereNotIn('status', [ReturnStatus::Rejected->value, ReturnStatus::Completed->value])
                ->exists();

            if ($existingReturn) {
                throw new \InvalidArgumentException('A return request already exists for this order.');
            }

            if (! in_array($order->status->value, [OrderStatus::Delivered->value, OrderStatus::Shipped->value])) {
                throw new \InvalidArgumentException('Order must be delivered or shipped to request a return.');
            }

            $windowDays = (int) config('app.returns.window_days', 30);
            $deliveredAt = OrderStatusHistory::where('order_id', $order->getKey())
                ->where('to_status', OrderStatus::Delivered->value)
                ->latest('created_at')
                ->value('created_at');

            // Carbon 3's diffInDays() is signed: now()->diffInDays(<past>) is
            // negative, so the old `> $windowDays` test could never be true and
            // the window was never enforced. Compare instants instead.
            if ($deliveredAt && Carbon::parse($deliveredAt)->lt(now()->subDays($windowDays))) {
                throw new \InvalidArgumentException("The return window ({$windowDays} days from delivery) for this order has expired.");
            }

            if (empty($data['items'])) {
                throw new \InvalidArgumentException('At least one item must be returned.');
            }

            if (! empty($data['items'])) {
                $orderItemIds = collect($data['items'])->pluck('order_item_id')->filter()->unique()->values()->all();
                // Lock order items during validation to prevent race condition
                $orderItems = OrderItem::lockForUpdate()->whereIn('_id', $orderItemIds)
                    ->with('product')
                    ->get()
                    ->keyBy('_id');

                foreach ($data['items'] as $item) {
                    $orderItem = $orderItems->get($item['order_item_id']);

                    if (! $orderItem || $orderItem->order_id !== $order->getKey()) {
                        throw new \InvalidArgumentException('One or more items do not belong to this order.');
                    }

                    if (! $orderItem->product?->return_allowed) {
                        throw new \InvalidArgumentException("Product '{$orderItem->product_name}' does not allow returns.");
                    }
                }
            }

            $return = $this->return->create([
                'order_id' => $order->getKey(),
                'customer_id' => $order->customer_id,
                'description' => $data['reason'] ?? null,
                'status' => ReturnStatus::Requested->value,
                'return_number' => 'RET-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $orderItem = $orderItems->get($item['order_item_id']);

                    if (! $orderItem || $orderItem->order_id !== $order->getKey()) {
                        throw new \InvalidArgumentException('One or more items do not belong to this order.');
                    }

                    $alreadyReturned = ReturnItem::where('order_item_id', $orderItem->getKey())
                        ->whereHas('return', fn ($q) => $q->where('status', '!=', ReturnStatus::Rejected->value))
                        ->sum('quantity');

                    $available = $orderItem->quantity - $alreadyReturned;
                    if ($item['quantity'] <= 0 || $item['quantity'] > $available) {
                        throw new \InvalidArgumentException('Return quantity exceeds the available quantity for one or more items.');
                    }

                    ReturnItem::create([
                        'return_id' => $return->getKey(),
                        'order_item_id' => $orderItem->getKey(),
                        'quantity' => $item['quantity'],
                        'return_reason_id' => $item['return_reason_id'] ?? null,
                        'description' => $item['reason'] ?? null,
                    ]);
                }
            }

            ReturnStatusHistory::create([
                'return_id' => $return->getKey(),
                'from_status' => null,
                'to_status' => ReturnStatus::Requested->value,
                'note' => 'Return request submitted.',
            ]);

            return $return->load(['items.orderItem', 'order']);
        });
    }

    public function approve(string $id, ?string $note = null): ReturnModel
    {
        return DB::transaction(function () use ($id, $note) {
            $return = $this->return->lockForUpdate()->findOrFail($id);

            $this->assertTransition($return->status->value, ReturnStatus::Approved->value);

            $fromStatus = $return->status->value;
            $return->update(['status' => ReturnStatus::Approved->value]);

            ReturnStatusHistory::create([
                'return_id' => $return->getKey(),
                'from_status' => $fromStatus,
                'to_status' => ReturnStatus::Approved->value,
                'note' => $note ?? 'Return approved.',
            ]);

            return $return->fresh()->load(['items.orderItem', 'order']);
        });
    }

    public function reject(string $id, ?string $note = null): ReturnModel
    {
        return DB::transaction(function () use ($id, $note) {
            $return = $this->return->lockForUpdate()->findOrFail($id);

            $this->assertTransition($return->status->value, ReturnStatus::Rejected->value);

            $fromStatus = $return->status->value;
            $return->update(['status' => ReturnStatus::Rejected->value]);

            ReturnStatusHistory::create([
                'return_id' => $return->getKey(),
                'from_status' => $fromStatus,
                'to_status' => ReturnStatus::Rejected->value,
                'note' => $note ?? 'Return rejected.',
            ]);

            return $return->fresh()->load(['items.orderItem', 'order']);
        });
    }

    public function receive(string $id, ?string $note = null): ReturnModel
    {
        return DB::transaction(function () use ($id, $note) {
            $return = $this->return->with('items.orderItem')->lockForUpdate()->findOrFail($id);

            $this->assertTransition($return->status->value, ReturnStatus::Returned->value);

            $fromStatus = $return->status->value;
            $return->update(['status' => ReturnStatus::Returned->value]);

            ReturnStatusHistory::create([
                'return_id' => $return->getKey(),
                'from_status' => $fromStatus,
                'to_status' => ReturnStatus::Returned->value,
                'note' => $note ?? 'Return received.',
            ]);

            return $return->fresh()->load(['items.orderItem', 'order']);
        });
    }

    public function complete(string $id, ?string $note = null): ReturnModel
    {
        return DB::transaction(function () use ($id, $note) {
            $return = $this->return->with('items.orderItem.product')->lockForUpdate()->findOrFail($id);

            $this->assertTransition($return->status->value, ReturnStatus::Completed->value);

            foreach ($return->items as $item) {
                $orderItem = $item->orderItem;
                $this->inventoryService->restoreStock(
                    $orderItem->product_id,
                    $orderItem->variant_id,
                    $item->quantity,
                    $return->order_id
                );
            }

            $fromStatus = $return->status->value;
            $return->update(['status' => ReturnStatus::Completed->value]);

            ReturnStatusHistory::create([
                'return_id' => $return->getKey(),
                'from_status' => $fromStatus,
                'to_status' => ReturnStatus::Completed->value,
                'note' => $note ?? 'Return completed.',
            ]);

            $this->handleCommissionsOnReturn($return);
            $this->settleOrder($return);
            $this->refundOnReturn($return);

            return $return->fresh()->load(['items.orderItem', 'order']);
        });
    }

    /**
     * Settle order after returns are completed.
     *
     * Writes OrderStatusHistory via OrderService::updateStatus when appropriate.
     * Never rewind to processing.
     */
    protected function settleOrder(ReturnModel $return): void
    {
        $order = Order::lockForUpdate()->with(['items', 'returns.items'])->find($return->order_id);

        if (! $order) {
            return;
        }

        $settleable = [
            OrderStatus::Shipped->value,
            OrderStatus::OutForDelivery->value,
            OrderStatus::Delivered->value,
            OrderStatus::Returned->value,
        ];

        if (! in_array($order->status->value ?? $order->status, $settleable, true)) {
            return;
        }

        $returnedQuantities = [];
        $orderItems = $order->items->keyBy('_id');
        foreach ($order->returns as $r) {
            // Only count completed returns
            if ($r->status !== ReturnStatus::Completed->value) {
                continue;
            }
            foreach ($r->items as $item) {
                $key = $item->order_item_id;
                $returnedQuantities[$key] = ($returnedQuantities[$key] ?? 0) + $item->quantity;
            }
        }

        $isFullyReturned = true;
        foreach ($orderItems as $id => $orderItem) {
            $returned = $returnedQuantities[$id] ?? 0;
            if ($returned < $orderItem->quantity) {
                $isFullyReturned = false;
                break;
            }
        }

        if ($isFullyReturned && $order->status->value !== OrderStatus::Returned->value) {
            $this->orderService->updateStatus(
                $order->getKey(),
                OrderStatus::Returned->value,
                "Return #{$return->return_number} completed."
            );

            return;
        }

        if (! $isFullyReturned) {
            $this->orderService->updateStatus(
                $order->getKey(),
                $order->status->value,
                'Partial return completed'
            );
        }
    }

    protected function assertTransition(string $from, string $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new \Exception("Cannot transition return from '{$from}' to '{$to}'.");
        }
    }

    protected function handleCommissionsOnReturn(ReturnModel $return): void
    {
        $order = Order::find($return->order_id);
        if (! $order) {
            return;
        }

        $commissions = AffiliateCommission::with('affiliate')->where('order_id', $order->getKey())->get();
        $totalReturnedQty = $order->returns()
            ->whereIn('status', [ReturnStatus::Completed->value])
            ->with('items')
            ->get()
            ->flatMap(fn ($r) => $r->items)
            ->sum('quantity');
        $orderQty = $order->items()->sum('quantity');

        // If full return is complete contextually, reject pending commissions
        $fullyReturned = $totalReturnedQty >= $orderQty;
        if ($fullyReturned) {
            foreach ($commissions as $commission) {
                if ($commission->status === 'pending') {
                    DB::transaction(function () use ($commission) {
                        $affiliate = $commission->affiliate;
                        if ($affiliate) {
                            $affiliate->decrement('total_earned', (float) $commission->amount);
                        }
                        $commission->update(['status' => 'rejected']);
                    });
                } elseif ($commission->status === 'approved') {
                    DB::transaction(function () use ($commission) {
                        $affiliate = $commission->affiliate;
                        if ($affiliate) {
                            $affiliate->decrement('total_earned', (float) $commission->amount);
                        }
                        $commission->update(['status' => 'rejected']);
                    });
                }
            }
        }
    }

    protected function refundOnReturn(ReturnModel $return): void
    {
        if (! $this->refundService) {
            return;
        }

        $order = Order::find($return->order_id);
        if (! $order) {
            return;
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            try {
                $this->refundService->refund($order);
            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }

    public function getCustomerReturns(string $customerId): LengthAwarePaginator
    {
        return $this->return->with(['order', 'items.orderItem'])
            ->where('customer_id', $customerId)
            ->latest()
            ->paginate(15);
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = $this->return->with(['customer', 'order', 'items.orderItem']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        $sortBy = in_array($filters['sort_by'] ?? '', self::ALLOWED_SORT_COLUMNS, true)
            ? $filters['sort_by'] : 'created_at';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_dir']) : 'desc';
        $query->orderBy($sortBy, $sortDir);

        // `per_page` comes from the query string; an unbounded or non-positive
        // value would load the whole table or break the paginator.
        return $query->paginate(max(1, min(100, (int) ($filters['per_page'] ?? 15))));
    }
}
