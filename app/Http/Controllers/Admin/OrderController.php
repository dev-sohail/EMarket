<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin('orders.view');

        // Validated rather than trusted. `status` and `payment_status` reach a
        // where clause directly, so an unrecognised value used to return a
        // silently empty list instead of reporting the typo, and `per_page`
        // used to reach the paginator uncapped.
        $request->validate([
            'status' => 'nullable|in:'.implode(',', array_column(OrderStatus::cases(), 'value')),
            'payment_status' => 'nullable|in:'.implode(',', array_column(PaymentStatus::cases(), 'value')),
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|in:_id,order_number,status,payment_status,grand_total,created_at,updated_at',
            'sort_dir' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $filters = $request->only([
            'status', 'search', 'date_from', 'date_to', 'payment_status', 'sort_by', 'sort_dir', 'per_page',
        ]);

        $orders = $this->orderService->getAll($filters);

        // The summary describes the whole book, not the filtered page, so it is
        // counted separately from the listing.
        $stats = $this->orderService->getSummaryStats();

        return view('admin.order.index', compact('orders', 'filters', 'stats'));
    }

    public function show(Order $order)
    {
        $this->authorizeAdmin('orders.view');
        $order->load([
            'customer',
            'items.product',
            'items.variant.product',
            'shippingAddress',
            'billingAddress',
            'charges',
            'statusHistories',
            'invoices',
            'returns',
        ]);

        return view('admin.order.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeAdmin('orders.update');
        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,ready_to_ship,shipped,out_for_delivery,delivered,cancelled',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $this->orderService->updateStatus(
                $order->getKey(),
                $request->input('status'),
                $request->input('note')
            );
        } catch (\Exception $e) {
            report($e);

            return back()->withInput()->with('error', 'Failed to update order status. Please try again.');
        }

        return back()->with('success', 'Order status updated successfully.');
    }

    public function cancel(Order $order)
    {
        $this->authorizeAdmin('orders.cancel');

        try {
            $this->orderService->cancel($order->getKey());
        } catch (\Exception $e) {
            report($e);

            return back()->with('error', 'Failed to cancel order. Please try again.');
        }

        return back()->with('success', 'Order cancelled successfully.');
    }

    public function printOrder(Order $order)
    {
        $this->authorizeAdmin('orders.view');
        $order->load([
            'customer',
            'items.product',
            'items.variant.product',
            'shippingAddress',
            'billingAddress',
            'charges',
        ]);

        return view('admin.order.print', compact('order'));
    }

    /**
     * Deny the request unless the admin guard user holds $permission.
     *
     * Belt and braces over AdminMiddleware: config('admin.route_permissions')
     * maps the two mutating OrderController routes (admin.orders.status and
     * admin.orders.cancel) but not the read actions, so index/show/print would
     * otherwise be reachable by any user holding any role.
     *
     * Permission names must exist in PermissionSeeder; they are `module.action`
     * pairs, and an unknown one denies every role, Super Admin included.
     */
    protected function authorizeAdmin(string $permission): void
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin || ! $admin->hasPermission($permission)) {
            abort(403, "You do not have the '{$permission}' permission.");
        }
    }
}
