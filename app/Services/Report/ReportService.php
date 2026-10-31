<?php

namespace App\Services\Report;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn as ReturnModel;
use Carbon\Carbon;

class ReportService
{
    public function salesReport(array $dateRange = []): array
    {
        $query = Order::where('payment_status', PaymentStatus::Paid->value)
            ->whereNotIn('status', OrderStatus::nonRevenue());

        if (! empty($dateRange['from'])) {
            $query->where('created_at', '>=', $dateRange['from']);
        }
        if (! empty($dateRange['to'])) {
            $query->where('created_at', '<=', Carbon::parse($dateRange['to'])->endOfDay());
        }

        $orders = $query->get();

        return [
            'total_sales' => $orders->sum('grand_total'),
            'total_orders' => $orders->count(),
            'average_order_value' => $orders->avg('grand_total'),
            'total_discount' => $orders->sum('discount'),
            'total_tax' => $orders->sum('tax'),
            'total_shipping' => $orders->sum('shipping_cost'),
            'daily_sales' => $orders->groupBy(fn ($o) => $o->created_at->format('Y-m-d'))
                ->map(fn ($group) => [
                    'date' => $group->first()->created_at->format('Y-m-d'),
                    'sales' => $group->sum('grand_total'),
                    'orders' => $group->count(),
                ])->values(),
        ];
    }

    public function ordersReport(array $dateRange = []): array
    {
        $query = Order::query();

        if (! empty($dateRange['from'])) {
            $query->where('created_at', '>=', $dateRange['from']);
        }
        if (! empty($dateRange['to'])) {
            $query->where('created_at', '<=', $dateRange['to']);
        }

        $orders = $query->get();

        return [
            'total_orders' => $orders->count(),
            'by_status' => $orders->groupBy(fn ($o) => $o->status->value)->map(fn ($group) => [
                'status' => $group->first()->status->value,
                'count' => $group->count(),
                'total' => $group->sum('grand_total'),
            ])->values(),
            'by_payment_method' => $orders->groupBy('payment_method')->map(fn ($group) => [
                'method' => $group->first()->payment_method_label,
                'count' => $group->count(),
                'total' => $group->sum('grand_total'),
            ])->values(),
            'by_payment_status' => $orders->groupBy(fn ($o) => $o->payment_status->value)->map(fn ($group) => [
                'status' => $group->first()->payment_status->value,
                'count' => $group->count(),
                'total' => $group->sum('grand_total'),
            ])->values(),
        ];
    }

    public function productsReport(array $dateRange = []): array
    {
        $query = Product::withSum(['orderItems' => function ($q) use ($dateRange) {
            if (! empty($dateRange['from'])) {
                $q->whereHas('order', fn ($oq) => $oq->where('created_at', '>=', $dateRange['from']));
            }
            if (! empty($dateRange['to'])) {
                $q->whereHas('order', fn ($oq) => $oq->where('created_at', '<=', $dateRange['to']));
            }
        }], 'quantity as total_sold');

        // Eager-load inventory so the low-stock pass does not query once per
        // product, and take the threshold from the settings instead of a
        // hardcoded five. An unset threshold means "no low-stock reporting".
        $threshold = low_stock_threshold();

        $products = $query->with('inventory')->get();

        return [
            'total_products' => $products->count(),
            'active_products' => $products->where('is_active', true)->count(),
            'top_selling' => $products->sortByDesc('total_sold')->take(10)->values(),
            'low_stock' => $threshold === null
                ? collect()
                : $products->filter(fn ($p) => $p->inventory->sum('quantity') < $threshold)->values(),
        ];
    }

    public function inventoryReport(): array
    {
        $inventory = Inventory::with(['product', 'variant'])->get();

        return [
            'total_items' => $inventory->count(),
            'total_stock' => $inventory->sum('quantity'),
            'total_reserved' => $inventory->sum('reserved_quantity'),
            // `reorder_level` is the per-row threshold and is NOT NULL, so every
            // row has one. Reading the old `low_stock_threshold` attribute
            // returned null here, and `quantity <= null` is false for every row,
            // which left this list permanently empty.
            'low_stock_items' => $inventory->filter(
                fn ($i) => $i->quantity > 0 && $i->quantity <= $i->reorder_level
            )->values(),
            'out_of_stock' => $inventory->filter(fn ($i) => $i->quantity <= 0)->values(),
            'by_product' => $inventory->groupBy('product_id')->map(fn ($items, $productId) => [
                'product_id' => $productId,
                'total_quantity' => $items->sum('quantity'),
                'variants' => $items->count(),
            ])->values(),
        ];
    }

    public function customersReport(array $dateRange = []): array
    {
        $query = Customer::withCount('orders');

        if (! empty($dateRange['from'])) {
            $query->where('created_at', '>=', $dateRange['from']);
        }
        if (! empty($dateRange['to'])) {
            $query->where('created_at', '<=', $dateRange['to']);
        }

        $customers = $query->get();

        return [
            'total_customers' => $customers->count(),
            // "New" means a one-time buyer: exactly one order. Reporting the total
            // here made this figure identical to total_customers on every run.
            'new_customers' => $customers->filter(fn ($c) => $c->orders_count === 1)->count(),
            'repeat_customers' => $customers->filter(fn ($c) => $c->orders_count > 1)->count(),
            'top_customers' => $customers->sortByDesc('orders_count')->take(10)->values(),
        ];
    }

    public function returnsReport(array $dateRange = []): array
    {
        $query = ReturnModel::with(['items']);

        if (! empty($dateRange['from'])) {
            $query->where('created_at', '>=', $dateRange['from']);
        }
        if (! empty($dateRange['to'])) {
            $query->where('created_at', '<=', $dateRange['to']);
        }

        $returns = $query->get();

        return [
            'total_returns' => $returns->count(),
            'by_status' => $returns->groupBy('status')->map(fn ($group) => [
                'status' => $group->first()->status,
                'count' => $group->count(),
            ])->values(),
            'total_returned_items' => $returns->sum(fn ($r) => $r->items->sum('quantity')),
        ];
    }

    public function couponsReport(array $dateRange = []): array
    {
        $query = Coupon::withCount('usages')->with('usages.order');

        if (! empty($dateRange['from'])) {
            $query->where('created_at', '>=', $dateRange['from']);
        }
        if (! empty($dateRange['to'])) {
            $query->where('created_at', '<=', $dateRange['to']);
        }

        $coupons = $query->get();

        return [
            'total_coupons' => $coupons->count(),
            'active_coupons' => $coupons->where('is_active', true)->count(),
            'total_usage' => $coupons->sum('used_count'),
            'top_coupons' => $coupons->sortByDesc('used_count')->take(10)->values(),
            'total_discount_given' => $coupons->sum(
                fn ($c) => $c->usages->sum(fn ($usage) => (float) ($usage->order?->discount ?? 0))
            ),
        ];
    }

    public function export(string $type, array $data = []): array
    {
        return match ($type) {
            'sales' => $this->salesReport($data['date_range'] ?? []),
            'orders' => $this->ordersReport($data['date_range'] ?? []),
            'products' => $this->productsReport($data['date_range'] ?? []),
            'inventory' => $this->inventoryReport(),
            'customers' => $this->customersReport($data['date_range'] ?? []),
            'returns' => $this->returnsReport($data['date_range'] ?? []),
            'coupons' => $this->couponsReport($data['date_range'] ?? []),
            default => throw new \Exception("Unknown report type: {$type}"),
        };
    }
}
