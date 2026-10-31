<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $customer = auth()->user()->customer;

        $query = Order::with(['items.product'])
            ->where('customer_id', $customer->getKey());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        return view('customer.order.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $customer = auth()->user()->customer;

        abort_if($order->customer_id !== $customer->getKey(), 403);

        $order->load([
            'items.product',
            'items.variant.product',
            'shippingAddress',
            'billingAddress',
            'charges',
            'statusHistories',
        ]);

        return view('customer.order.show', compact('order'));
    }

    public function trackForm()
    {
        return view('customer.order.track');
    }

    public function track(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email|string',
        ]);

        $email = $request->input('email');
        $phone = $request->input('phone');

        if (! $email && ! $phone) {
            return back()->with('error', 'Please provide either your email address or phone number used at checkout.');
        }

        $orderNumber = $request->input('order_number');

        // Build query with optimized where conditions
        // Consider adding indexes on: orders.order_number, customers.email, customers.phone, customer_addresses.phone
        $query = Order::where('order_number', $orderNumber)
            ->with(['customer', 'user', 'shippingAddress', 'billingAddress']);

        if ($email) {
            $query->where(function ($q) use ($email) {
                $q->whereHas('customer', fn ($q2) => $q2->where('email', $email))
                    ->orWhereHas('user', fn ($q2) => $q2->where('email', $email));
            });
        }

        if ($phone) {
            $query->where(function ($q) use ($phone) {
                $q->whereHas('shippingAddress', fn ($q2) => $q2->where('phone', $phone))
                    ->orWhereHas('billingAddress', fn ($q2) => $q2->where('phone', $phone))
                    ->orWhereHas('customer', fn ($q2) => $q2->where('phone', $phone));
            });
        }

        $order = $query->first();

        if (! $order) {
            return back()->with('error', 'Order not found or the details do not match.');
        }

        return view('customer.order.track', compact('order'));
    }
}
