<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Services\Order\OrderService;

class DashboardController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index()
    {
        $customer = auth()->user()->customer;

        if ($customer && $customer->trashed()) {
            $customer->restore();
        }

        if (! $customer) {
            $customer = $this->orderService->createCustomerFromUser(auth()->user());
        }

        $recentOrders = $this->orderService->getCustomerOrders($customer->getKey());

        $orderStats = $customer->orders()
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status != ? THEN grand_total ELSE 0 END) as total_spent
            ', [OrderStatus::Pending->value, OrderStatus::Delivered->value, OrderStatus::Cancelled->value])
            ->first();

        $stats = [
            'total_orders' => (int) ($orderStats->total_orders ?? 0),
            'pending_orders' => (int) ($orderStats->pending_orders ?? 0),
            'completed_orders' => (int) ($orderStats->completed_orders ?? 0),
            'total_spent' => (float) ($orderStats->total_spent ?? 0),
        ];

        return view('customer.dashboard', compact('customer', 'recentOrders', 'stats'));
    }
}
