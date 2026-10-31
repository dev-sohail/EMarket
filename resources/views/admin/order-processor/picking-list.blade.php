@extends('layouts.admin')
@section('title', 'Picking List - Order #' . ($order->order_number ?? ''))
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Picking List</h1>
            <p class="text-sm text-[var(--color-steel)] mt-1">Order #{{ $order->order_number }}</p>
        </div>
        <div class="flex items-center gap-3">
            <button data-action="print" class="bg-[var(--color-red)] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                <i class="fa-solid fa-print mr-1"></i> Print
            </button>
            <a href="{{ route('admin.order-processor.index') }}" class="border hairline px-4 py-2 rounded text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Back to Processor</a>
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
        <div class="p-6 border-b hairline">
            <h2 class="font-semibold text-[var(--color-ink)]">Order Details</h2>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-[var(--color-steel)]">Order Number</p>
                    <p class="font-medium text-[var(--color-ink)]">{{ $order->order_number }}</p>
                </div>
                <div>
                    <p class="text-[var(--color-steel)]">Date</p>
                    <p class="font-medium text-[var(--color-ink)]">{{ $order->created_at->format('M d, Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-[var(--color-steel)]">Customer</p>
                    <p class="font-medium text-[var(--color-ink)]">{{ $order->customer->full_name ?? $order->user?->name ?? 'Guest' }}</p>
                </div>
                <div>
                    <p class="text-[var(--color-steel)]">Payment Method</p>
                    <p class="font-medium text-[var(--color-ink)]">{{ $order->payment_method_label }}</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <h3 class="font-semibold text-[var(--color-ink)] mb-4">Items to Pick</h3>
            <table class="w-full">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">SKU</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Product</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Variant</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($order->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-sm text-[var(--color-ink)]">{{ $item->sku }}</td>
                            <td class="px-4 py-3 text-sm text-[var(--color-ink)]">{{ $item->product_name }}</td>
                            <td class="px-4 py-3 text-sm text-[var(--color-steel)]">{{ $item->variant_name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-[var(--color-ink)] text-right">{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t hairline">
            <h3 class="font-semibold text-[var(--color-ink)] mb-2">Shipping Address</h3>
            @if($order->shippingAddress)
                <p class="text-sm text-[var(--color-ink)]">{{ $order->shippingAddress->full_name }}</p>
                <p class="text-sm text-[var(--color-steel)]">{{ $order->shippingAddress->address_line_1 }}</p>
                @if($order->shippingAddress->address_line_2)
                    <p class="text-sm text-[var(--color-steel)]">{{ $order->shippingAddress->address_line_2 }}</p>
                @endif
                <p class="text-sm text-[var(--color-steel)]">{{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }} {{ $order->shippingAddress->postal_code }}</p>
            @else
                <p class="text-sm text-[var(--color-steel)]">No address on file</p>
            @endif
        </div>
    </div>
</div>
@endsection
