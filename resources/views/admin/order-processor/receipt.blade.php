@extends('layouts.admin')
@section('title', 'Receipt - Order #' . ($receipt['order_number'] ?? ''))
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Receipt</h1>
            <p class="text-sm text-[var(--color-steel)] mt-1">Order #{{ $receipt['order_number'] ?? 'N/A' }}</p>
        </div>
        <div class="flex items-center gap-3">
            <button data-action="print" class="bg-[var(--color-red)] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                <i class="fa-solid fa-print mr-1"></i> Print Receipt
            </button>
            <a href="{{ route('admin.order-processor.index') }}" class="border hairline px-4 py-2 rounded text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Back to Processor</a>
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-8 max-w-2xl mx-auto">
        <div class="text-center border-b hairline pb-4 mb-4">
            <h2 class="text-lg font-bold text-[var(--color-ink)]">RECEIPT</h2>
            <p class="text-sm text-[var(--color-steel)]">Order #{{ $receipt['order_number'] }}</p>
            <p class="text-sm text-[var(--color-steel)]">{{ $receipt['date'] }}</p>
        </div>

        <div class="space-y-4 text-sm">
            <div>
                <p class="text-[var(--color-steel)]">Customer</p>
                <p class="font-medium text-[var(--color-ink)]">{{ $receipt['customer_name'] }}</p>
                <p class="text-[var(--color-steel)]">{{ $receipt['customer_email'] }}</p>
            </div>
            <div>
                <p class="text-[var(--color-steel)]">Shipping Address</p>
                <p class="text-[var(--color-ink)]">{{ $receipt['shipping_address'] }}</p>
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[var(--color-steel)]">Payment Method</p>
                    <p class="text-[var(--color-ink)]">{{ $receipt['payment_method'] }}</p>
                </div>
                <div>
                    <p class="text-[var(--color-steel)]">Payment Status</p>
                    <p class="text-[var(--color-ink)]">{{ $receipt['payment_status'] }}</p>
                </div>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t hairline">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[var(--color-steel)]">
                        <th class="text-left pb-2">Item</th>
                        <th class="text-right pb-2">Qty</th>
                        <th class="text-right pb-2">Price</th>
                        <th class="text-right pb-2">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($receipt['items'] as $item)
                        <tr>
                            <td class="py-2 text-[var(--color-ink)]">
                                {{ $item['name'] }}
                                @if($item['variant'])
                                    <span class="text-[var(--color-steel)]">({{ $item['variant'] }})</span>
                                @endif
                            </td>
                            <td class="py-2 text-[var(--color-ink)] text-right">{{ $item['quantity'] }}</td>
                            <td class="py-2 text-[var(--color-ink)] text-right">{{ format_currency($item['unit_price']) }}</td>
                            <td class="py-2 text-[var(--color-ink)] text-right">{{ format_currency($item['line_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 pt-4 border-t hairline space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-[var(--color-steel)]">Subtotal</span>
                <span class="text-[var(--color-ink)]">{{ format_currency($receipt['subtotal']) }}</span>
            </div>
            @if($receipt['discount'] > 0)
                <div class="flex justify-between text-sm">
                    <span class="text-[var(--color-steel)]">Discount</span>
                    <span class="text-[#1B7A43]">-{{ format_currency($receipt['discount']) }}</span>
                </div>
            @endif
            <div class="flex justify-between text-sm">
                <span class="text-[var(--color-steel)]">Shipping</span>
                <span class="text-[var(--color-ink)]">{{ format_currency($receipt['shipping_cost']) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-[var(--color-steel)]">Tax</span>
                <span class="text-[var(--color-ink)]">{{ format_currency($receipt['tax']) }}</span>
            </div>
            <div class="flex justify-between text-lg font-bold border-t hairline pt-2">
                <span class="text-[var(--color-ink)]">Total</span>
                <span class="text-[var(--color-ink)]">{{ format_currency($receipt['grand_total']) }}</span>
            </div>
        </div>
    </div>
</div>
@endsection
