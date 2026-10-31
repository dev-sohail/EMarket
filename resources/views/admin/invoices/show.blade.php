@extends('layouts.admin')

@section('title', 'Invoice #' . ($invoice->invoice_number ?? ''))

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Invoice #{{ $invoice->invoice_number ?? '' }}</h1>
    <div class="flex gap-2">
        <a href="{{ route('admin.invoices.download', $invoice->getKey()) }}" class="bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-line)] transition">Download PDF</a>
        <a href="{{ route('admin.invoices.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Invoices</a>
    </div>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Invoice Items</h2>
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Product</th>
                        <th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Qty</th>
                        <th class="text-right px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Price</th>
                        <th class="text-right px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($invoice->items ?? [] as $item)
                        <tr>
                            <td class="px-4 py-3 text-[var(--color-ink)]">{{ $item->product_name ?? '-' }}</td>
                            <td class="px-4 py-3 text-[var(--color-steel)]">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-right text-[var(--color-steel)]">{{ format_currency($item->unit_price) }}</td>
                            <td class="px-4 py-3 text-right font-medium">{{ format_currency($item->line_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-4 text-center text-[var(--color-steel)]">No items.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t mt-4 pt-4 space-y-2 max-w-xs ml-auto">
                <div class="flex justify-between text-sm"><span class="text-[var(--color-steel)]">Subtotal</span><span>{{ format_currency($invoice->subtotal ?? 0) }}</span></div>
                @if(($invoice->discount ?? 0) > 0)
                    <div class="flex justify-between text-sm text-[#1B7A43]"><span>Discount</span><span>-{{ format_currency($invoice->discount) }}</span></div>
                @endif
                @if(($invoice->tax ?? 0) > 0)
                    <div class="flex justify-between text-sm"><span class="text-[var(--color-steel)]">Tax</span><span>{{ format_currency($invoice->tax) }}</span></div>
                @endif
                <div class="flex justify-between text-lg font-bold border-t pt-2"><span>Total</span><span>{{ format_currency($invoice->grand_total ?? 0) }}</span></div>
            </div>
        </div>
    </div>
    <div class="space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-3">Invoice Info</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-[var(--color-steel)]">Status</span>
                    @php
                        $statusColors = ['paid' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'pending' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'];
                        $color = $statusColors[$invoice->payment_status] ?? 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]';
                    @endphp
                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $color }}">{{ ucfirst($invoice->payment_status) }}</span>
                </div>
                <div class="flex justify-between"><span class="text-[var(--color-steel)]">Order</span><span>#{{ $invoice->order->order_number ?? $invoice->order_id }}</span></div>
                <div class="flex justify-between"><span class="text-[var(--color-steel)]">Due Date</span><span>{{ $invoice->due_at?->format('M d, Y') ?? '-' }}</span></div>
            </div>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-3">Customer</h2>
            <div class="text-sm text-[var(--color-steel)] space-y-1">
                @php
                    $customer = $invoice->customer ?? $invoice->order->customer;
                    $user = $invoice->order->user;
                    $shipping = $invoice->order->shippingAddress;

                    $name = $customer
                        ? trim($customer->first_name . ' ' . $customer->last_name)
                        : ($user->name ?? $shipping->full_name ?? 'Guest');
                    $email = $customer->email ?? $user->email ?? null;
                    $phone = $customer->phone ?? $user->phone ?? $shipping->phone ?? null;
                @endphp
                <p class="font-medium text-[var(--color-ink)]">{{ $name }}</p>
                @if($email)
                    <p>{{ $email }}</p>
                @endif
                @if($phone)
                    <p>Phone: {{ $phone }}</p>
                @endif
                @if($invoice->order->shippingAddress)
                    <p class="mt-2 text-[var(--color-steel)]">{{ $invoice->order->shippingAddress->address_line_1 }}</p>
                    @if($invoice->order->shippingAddress->address_line_2)
                        <p class="text-[var(--color-steel)]">{{ $invoice->order->shippingAddress->address_line_2 }}</p>
                    @endif
                    <p class="text-[var(--color-steel)]">{{ $invoice->order->shippingAddress->city }}, {{ $invoice->order->shippingAddress->state }} {{ $invoice->order->shippingAddress->postal_code }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
