<?php

namespace App\Services\Order;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Exceptions\InvalidOrderStatusException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\AffiliateCommission;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethod;
use App\Models\ReturnItem;
use App\Services\Audit\AuditService;
use App\Services\Inventory\InventoryService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    private const ALLOWED_SORT_COLUMNS = [
        '_id', 'order_number', 'status', 'payment_status', 'grand_total', 'created_at', 'updated_at',
    ];

    private const DEFAULT_PER_PAGE = 15;

    private const MIN_PER_PAGE = 5;

    private const MAX_PER_PAGE = 100;

    /**
     * Legal order status transitions.
     *
     * Cancellation is only legal while the units are still under the merchant's
     * control. From `shipped` onwards the customer physically holds the goods,
     * so cancelling would restore stock the warehouse does not have. Post
     * shipment problems go through the return workflow instead:
     * shipped -> out_for_delivery -> delivered -> returned -> completed.
     *
     * `delivered` deliberately does not reach `processing`: a delivered order
     * has left the warehouse and must never be rewound, otherwise a return
     * approval can hand it back a free, restocking cancellation.
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['confirmed', 'processing', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready_to_ship', 'cancelled'],
        'ready_to_ship' => ['shipped', 'cancelled'],
        'shipped' => ['out_for_delivery', 'returned'],
        'out_for_delivery' => ['delivered', 'returned'],
        'delivered' => ['completed', 'returned'],
        'completed' => [],
        'returned' => [],
        'cancelled' => [],
    ];

    /**
     * Statuses from which the goods are no longer the merchant's to restock.
     *
     * Cancellation is refused from these rather than merely skipping the
     * restock: silently marking such an order cancelled while the customer
     * keeps the merchandise leaves the ledger and the warehouse disagreeing.
     */
    private const NON_RESTOCKABLE_STATUSES = [
        'shipped', 'out_for_delivery', 'delivered', 'returned', 'completed',
    ];

    public function __construct(
        protected Order $order,
        protected NotificationService $notificationService,
        protected AuditService $auditService,
        protected InventoryService $inventoryService
    ) {}

    public function verifyOrder(string $orderId): array
    {
        $order = Order::with(['items.product', 'items.variant.product', 'shippingAddress', 'billingAddress'])->findOrFail($orderId);

        $issues = [];

        if ($order->payment_status !== PaymentStatus::Paid && ! $this->isCodPayment($order)) {
            $issues[] = 'Payment not confirmed.';
        }

        foreach ($order->items as $item) {
            // Check if product/variant still exists and is active
            if ($item->variant_id) {
                if (! $item->variant || $item->variant->trashed() || ! $item->variant->is_active) {
                    $issues[] = "Variant for {$item->product_name} is no longer available.";
                }
            }

            if (! $item->product || $item->product->trashed() || ! $item->product->is_active) {
                $issues[] = "{$item->product_name} is no longer available.";
            }

            $available = $this->inventoryService->getAvailableStock($item->product_id, $item->variant_id);
            if ($available < $item->quantity) {
                $issues[] = "Insufficient stock for {$item->product_name} (need {$item->quantity}, have {$available}).";
            }
        }

        $shipping = $order->shippingAddress;
        if (! $shipping || ! $shipping->full_name || ! $shipping->address_line_1 || ! $shipping->city || ! $shipping->state || ! $shipping->postal_code) {
            $issues[] = 'Shipping address is incomplete.';
        }

        $billing = $order->billingAddress;
        if (! $billing || ! $billing->full_name || ! $billing->address_line_1 || ! $billing->city || ! $billing->state || ! $billing->postal_code) {
            $issues[] = 'Billing address is incomplete.';
        }

        return [
            'verified' => empty($issues),
            'issues' => $issues,
            'payment_status' => $order->payment_status->value,
            'shipping_address_valid' => $shipping && $shipping->full_name && $shipping->address_line_1 && $shipping->city && $shipping->state && $shipping->postal_code,
            'billing_address_valid' => $billing && $billing->full_name && $billing->address_line_1 && $billing->city && $billing->state && $billing->postal_code,
        ];
    }

    public function markReadyForFulfillment(string $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::lockForUpdate()->findOrFail($orderId);

            if ($order->status !== OrderStatus::Confirmed) {
                throw new \Exception('Order must be confirmed before fulfillment.');
            }

            $order->update([
                'fulfillment_status' => FulfillmentStatus::InWarehouse->value,
            ]);

            $this->notificationService->sendToAllAdmins(
                title: 'Order Ready for Fulfillment',
                message: "Order '{$order->order_number}' is ready for warehouse processing.",
                data: ['order_id' => $order->getKey()]
            );

            return $order->fresh();
        });
    }

    public function generateShippingLabel(string $orderId): array
    {
        $order = Order::with(['shippingAddress', 'items.product', 'items.variant.product'])->findOrFail($orderId);

        $totalWeight = 0;
        foreach ($order->items as $item) {
            $weight = $item->variant?->weight ?? $item->product?->weight ?? 0;
            $totalWeight += $weight * $item->quantity;
        }

        return [
            'order_number' => $order->order_number,
            'recipient' => $order->shippingAddress?->full_name ?? 'N/A',
            'address' => trim(($order->shippingAddress?->address_line_1 ?? '').' '.($order->shippingAddress?->address_line_2 ?? '')),
            'city' => $order->shippingAddress?->city ?? 'N/A',
            'state' => $order->shippingAddress?->state ?? 'N/A',
            'postal_code' => $order->shippingAddress?->postal_code ?? 'N/A',
            'phone' => $order->shippingAddress?->phone ?? 'N/A',
            'weight' => $totalWeight,
            'carrier' => $order->carrier ?? 'standard',
            'tracking_number' => $order->tracking_number,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'variant' => $item->variant_name,
                'quantity' => $item->quantity,
                'sku' => $item->sku,
            ])->toArray(),
        ];
    }

    public function generateReceipt(string $orderId): array
    {
        $order = Order::with(['items', 'charges', 'customer', 'user', 'shippingAddress'])->findOrFail($orderId);

        return [
            'order_number' => $order->order_number,
            'date' => $order->created_at?->format('Y-m-d H:i'),
            'customer_name' => $order->customer?->full_name ?? $order->user?->name ?? 'Guest',
            'customer_email' => $order->customer?->email ?? $order->user?->email ?? 'N/A',
            'shipping_address' => $order->shippingAddress ? trim($order->shippingAddress->full_name.', '.$order->shippingAddress->address_line_1.', '.$order->shippingAddress->city.', '.$order->shippingAddress->state.' '.$order->shippingAddress->postal_code) : 'N/A',
            'payment_method' => $order->payment_method_label,
            'payment_status' => ucfirst($order->payment_status?->value ?? 'pending'),
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'shipping_cost' => $order->shipping_cost,
            'tax' => $order->tax,
            'additional_charges' => $order->additional_charges,
            'grand_total' => $order->grand_total,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'variant' => $item->variant_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total ?? ($item->quantity * $item->unit_price),
            ])->toArray(),
        ];
    }

    protected function isCodPayment(Order $order): bool
    {
        if (in_array($order->payment_method, ['cod', 'cash_on_delivery'], true)) {
            return true;
        }

        return PaymentMethod::where('code', $order->payment_method)->where('is_cod', true)->exists();
    }

    /**
     * Headline figures for the orders list header.
     *
     * Counted over the whole order table rather than the filtered listing, so the
     * cards answer "how is the book doing" instead of restating the current page.
     * One grouped query rather than ten.
     *
     * `counts` carries one entry per OrderStatus case, because the list renders
     * a tab per case and any status missing here shows a tab with no count on
     * it however many orders sit in that state.
     *
     * @return array<string, int|float>
     */
    public function getSummaryStats(): array
    {
        $counts = Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $revenueStatuses = collect(OrderStatus::cases())
            ->reject(fn (OrderStatus $status): bool => in_array($status->value, OrderStatus::nonRevenue(), true))
            ->map(fn (OrderStatus $status): string => $status->value)
            ->all();

        $revenue = (float) Order::query()
            ->whereIn('status', $revenueStatuses)
            ->sum('grand_total');

        $perStatus = fn (string $status): int => (int) ($counts[$status] ?? 0);

        $stats = [
            'total' => (int) $counts->sum(),
            'revenue' => $revenue,
        ];

        foreach (OrderStatus::cases() as $case) {
            $stats[$case->value] = $perStatus($case->value);
        }

        // Cancelled and returned orders are excluded from the revenue above, so
        // they must be excluded from the divisor too: dividing net revenue by
        // the all-status order count understates the average order value by
        // however many orders were cancelled.
        $revenueOrderCount = array_sum(array_map(
            static fn (string $status): int => $perStatus($status),
            $revenueStatuses
        ));

        $stats['revenue_order_count'] = $revenueOrderCount;
        $stats['average_order'] = $revenueOrderCount > 0 ? $revenue / $revenueOrderCount : 0.0;

        return $stats;
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Order::with(['customer', 'user', 'items', 'shippingAddress'])
            ->withCount('items');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            // `user` is searched too because the list displays
            // `user->name` whenever an order has no customer record, so a
            // name shown in the table has to be searchable.
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q2) => $q2->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%");
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', self::ALLOWED_SORT_COLUMNS, true)
            ? $filters['sort_by'] : 'created_at';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_dir']) : 'desc';
        $query->orderBy($sortBy, $sortDir);

        // `per_page` arrives from the query string, so it is clamped rather
        // than trusted: an unbounded value asked the database to materialise
        // the entire order table on one page.
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $perPage = max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $perPage));

        return $query->paginate($perPage)->withQueryString();
    }

    public function getById(string $id): Order
    {
        return Order::with([
            'customer', 'items.product', 'items.variant.product',
            'shippingAddress', 'billingAddress', 'charges', 'statusHistories',
        ])->findOrFail($id);
    }

    public function getByNumber(string $number): Order
    {
        return Order::with([
            'customer', 'items.product', 'items.variant.product',
            'shippingAddress', 'billingAddress', 'charges', 'statusHistories',
        ])->where('order_number', $number)->firstOrFail();
    }

    public function updateStatus(string $orderId, string $status, ?string $note = null): Order
    {
        return DB::transaction(function () use ($orderId, $status, $note) {
            $order = Order::lockForUpdate()->findOrFail($orderId);
            $previousStatus = $order->status->value;

            $newStatus = OrderStatus::tryFrom($status);

            if (! $newStatus) {
                throw new InvalidOrderStatusException("Invalid order status: {$status}");
            }

            $allowedNext = self::ALLOWED_TRANSITIONS[$previousStatus] ?? [];

            if ($previousStatus !== $newStatus->value && ! in_array($newStatus->value, $allowedNext, true)) {
                throw new InvalidOrderTransitionException("Cannot transition order from '{$previousStatus}' to '{$status}'.");
            }

            $oldValues = $order->toArray();

            $updateData = ['status' => $newStatus->value];

            if ($newStatus === OrderStatus::Delivered && $this->isCodPayment($order) && $order->payment_status !== PaymentStatus::Paid) {
                $updateData['payment_status'] = PaymentStatus::Paid->value;
            }

            $order->update($updateData);

            if ($previousStatus !== $newStatus->value) {
                OrderStatusHistory::create([
                    'order_id' => $order->getKey(),
                    'from_status' => $previousStatus,
                    'to_status' => $newStatus->value,
                    'note' => $note,
                    'changed_by' => acting_user_id(),
                ]);
            }

            if ($newStatus === OrderStatus::Cancelled && $previousStatus !== OrderStatus::Cancelled->value) {
                $this->handleCancellation($order, $previousStatus);
            }

            $order->load('statusHistories');

            $previousLabel = OrderStatus::tryFrom($previousStatus)?->label() ?? $previousStatus;
            $newLabel = $newStatus->label();

            $this->notificationService->sendToAllAdmins(
                title: 'Order Status Updated',
                message: "Order '{$order->order_number}' status changed from {$previousLabel} to {$newLabel}.",
                data: ['order_id' => $order->getKey()]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'updated',
                entity: Order::class,
                entityId: $order->getKey(),
                oldValues: $oldValues,
                newValues: $order->fresh()->toArray()
            );

            return $order;
        });
    }

    public function cancel(string $orderId, ?string $note = null): Order
    {
        return $this->updateStatus($orderId, OrderStatus::Cancelled->value, $note ?? 'Order cancelled.');
    }

    public function getOrdersCountForDate(Carbon $date): int
    {
        return Order::whereDate('created_at', $date)->count();
    }

    public function getRevenueForDate(Carbon $date): float
    {
        // Same non-revenue set getSummaryStats() uses. Excluding only
        // `cancelled` here let returned orders inflate this figure while the
        // dashboard's own revenue card excluded them.
        return (float) Order::whereDate('created_at', $date)
            ->whereNotIn('status', OrderStatus::nonRevenue())
            ->sum('grand_total');
    }

    public function getRevenueForMonth(Carbon $date): float
    {
        return (float) Order::whereYear('created_at', $date->year)
            ->whereMonth('created_at', $date->month)
            ->whereNotIn('status', OrderStatus::nonRevenue())
            ->sum('grand_total');
    }

    public function getPendingOrdersCount(): int
    {
        return Order::where('status', 'pending')->count();
    }

    public function getRecentOrders(int $limit = 10)
    {
        return Order::with(['customer', 'items'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getOrdersCountByStatus(): array
    {
        return Order::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    public function getCustomerOrders(string $customerId): LengthAwarePaginator
    {
        return Order::with(['items.product', 'items.variant.product'])
            ->where('customer_id', $customerId)
            ->latest()
            ->paginate(15);
    }

    public function createCustomerFromUser($user)
    {
        // 1. Check if customer already exists for this user (including soft-deleted)
        $customer = Customer::withTrashed()
            ->where('user_id', $user->getKey())
            ->first();

        if ($customer) {
            // Restore if it was soft deleted
            if ($customer->trashed()) {
                $customer->restore();
            }

            return $customer;
        }

        // 2. Check if customer exists with this email (including soft-deleted)
        $customer = Customer::withTrashed()
            ->where('email', $user->email)
            ->first();

        if ($customer) {
            // Restore if it was soft deleted and update to link to this user
            $customer->restore();
            $customer->update([
                'user_id' => $user->getKey(),
                'first_name' => strtok($user->name ?? '', ' '),
                'last_name' => '',
                'phone' => $user->phone ?? '',
                'is_active' => true,
            ]);

            return $customer;
        }

        // 3. If no customer exists, create new one
        $customer = Customer::create([
            'email' => $user->email,
            'user_id' => $user->getKey(),
            'first_name' => strtok($user->name ?? '', ' '),
            'last_name' => '',
            'phone' => $user->phone ?? '',
            'is_active' => true,
        ]);

        return $customer;
    }

    public function getStatusHistory(string $orderId)
    {
        return OrderStatusHistory::where('order_id', $orderId)
            ->latest()
            ->get();
    }

    /**
     * Restore stock and coupon capacity for a cancelled order.
     *
     * Runs inside the caller's transaction, so a throw here rolls the status
     * change and the OrderStatusHistory row back with it: the status change and
     * the restock cannot half-apply. $previousStatus is the status the order
     * held *before* this cancellation, which is the value that decides whether
     * the units are still sellable.
     */
    protected function handleCancellation(Order $order, string $previousStatus): void
    {
        if (in_array($previousStatus, self::NON_RESTOCKABLE_STATUSES, true)) {
            throw new InvalidOrderTransitionException(
                'An order that has already been shipped cannot be cancelled; process a return or a refund instead.'
            );
        }

        $alreadyReturnedByItem = ReturnItem::whereHas('return', function ($query) use ($order) {
            $query->where('order_id', $order->getKey())
                ->whereIn('status', [ReturnStatus::Approved, ReturnStatus::Returned, ReturnStatus::Completed]);
        })->get()->groupBy('order_item_id')->map(fn ($items) => $items->sum('quantity'));

        foreach ($order->items as $item) {
            $alreadyReturned = (int) ($alreadyReturnedByItem[$item->getKey()] ?? 0);
            $quantityToRestock = max(0, $item->quantity - $alreadyReturned);

            if ($quantityToRestock > 0) {
                $this->inventoryService->restoreStock(
                    $item->product_id,
                    $item->variant_id,
                    $quantityToRestock,
                    $order->getKey()
                );
            }
        }

        $this->releaseCouponUsage($order);
        $this->rejectPendingAffiliateCommissions($order);
    }

    protected function releaseCouponUsage(Order $order): void
    {
        if ($order->coupon_code) {
            $coupon = Coupon::where('code', strtoupper($order->coupon_code))->first();
            if ($coupon) {
                $coupon->releaseUsage($order->getKey());
            }
        }
    }

    protected function rejectPendingAffiliateCommissions(Order $order): void
    {
        $commissions = AffiliateCommission::with('affiliate')->where('order_id', $order->getKey())->get();
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
