@extends('layouts.admin')
@section('title', 'Print Order #' . $order->order_number)
@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border p-8 max-w-4xl mx-auto print:p-0">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">{{ setting('store_name', '') }}</h1>
            <p class="text-[var(--color-steel)] text-sm">Order #{{ $order->order_number }}</p>
        </div>
        <div class="text-right">
            <p class="text-sm text-[var(--color-steel)]">Date: {{ $order->created_at->format('M d, Y') }}</p>
            <p class="text-sm">
                Status: <span class="font-medium">{{ $order->status?->label() ?? 'Unknown' }}</span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-8 mb-8">
        <div>
            <h3 class="font-semibold text-[var(--color-ink)] mb-2">Shipping Address</h3>
            @if($order->shippingAddress)
                <p class="text-sm text-[var(--color-steel)]">{{ $order->shippingAddress->address_line_1 }}</p>
                @if($order->shippingAddress->address_line_2)
                    <p class="text-sm text-[var(--color-steel)]">{{ $order->shippingAddress->address_line_2 }}</p>
                @endif
                <p class="text-sm text-[var(--color-steel)]">{{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }} {{ $order->shippingAddress->postal_code }}</p>
            @endif
        </div>
        <div>
            <h3 class="font-semibold text-[var(--color-ink)] mb-2">Customer</h3>
            <p class="text-sm text-[var(--color-steel)]">{{ $order->customer->first_name ?? '' }} {{ $order->customer->last_name ?? '' }}</p>
            <p class="text-sm text-[var(--color-steel)]">{{ $order->customer->email ?? '' }}</p>
            <p class="text-sm text-[var(--color-steel)]">{{ $order->customer->phone ?? '' }}</p>
        </div>
    </div>

    <table class="w-full mb-8">
        <thead class="bg-[var(--color-paper-dim)]">
            <tr>
                <th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Product</th>
                <th class="text-center px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Qty</th>
                <th class="text-right px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Price</th>
                <th class="text-right px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($order->items as $item)
                <tr>
                    <td class="px-4 py-3 text-sm">{{ $item->product->name ?? 'N/A' }}</td>
                    <td class="px-4 py-3 text-sm text-center">{{ $item->quantity }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ format_currency($item->unit_price) }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ format_currency($item->unit_price * $item->quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="flex justify-end">
        <div class="w-64 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-[var(--color-steel)]">Subtotal</span>
                <span>{{ format_currency($order->subtotal) }}</span>
            </div>
            @if($order->discount > 0)
                <div class="flex justify-between text-sm">
                    <span class="text-[var(--color-steel)]">Discount</span>
                    <span class="text-[var(--color-red)]">-{{ format_currency($order->discount) }}</span>
                </div>
            @endif
            <div class="flex justify-between text-sm">
                <span class="text-[var(--color-steel)]">Shipping</span>
                <span>{{ format_currency($order->shipping_cost) }}</span>
            </div>
            @if($order->tax > 0)
                <div class="flex justify-between text-sm">
                    <span class="text-[var(--color-steel)]">Tax</span>
                    <span>{{ format_currency($order->tax) }}</span>
                </div>
            @endif
            <div class="flex justify-between font-semibold text-lg border-t pt-2">
                <span>Total</span>
                <span>{{ format_currency($order->grand_total) }}</span>
            </div>
        </div>
    </div>

    <div class="mt-8 text-center">
        <p class="text-sm text-[var(--color-steel)]">Payment Method: {{ $order->payment_method_label }}</p>
    </div>

    <div class="mt-8 print:hidden text-center">
        <button data-action="print" class="bg-[var(--color-red)] text-white px-6 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)]">Print Order</button>
    </div>
</div>
@endsection
