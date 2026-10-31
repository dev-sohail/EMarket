@extends('layouts.admin')
@section('title', 'Shipping Label - Order #' . ($label['order_number'] ?? ''))
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Shipping Label</h1>
            <p class="text-sm text-[var(--color-steel)] mt-1">Order #{{ $label['order_number'] ?? 'N/A' }}</p>
        </div>
        <div class="flex items-center gap-3">
            <button data-action="print" class="bg-[var(--color-red)] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                <i class="fa-solid fa-print mr-1"></i> Print Label
            </button>
            <a href="{{ route('admin.order-processor.index') }}" class="border hairline px-4 py-2 rounded text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Back to Processor</a>
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-8 max-w-2xl mx-auto">
        <div class="border-b hairline pb-4 mb-4">
            <h2 class="text-lg font-bold text-[var(--color-ink)]">SHIPPING LABEL</h2>
            <p class="text-sm text-[var(--color-steel)]">Carrier: {{ strtoupper($label['carrier'] ?? 'STANDARD') }}</p>
            @if($label['tracking_number'])
                <p class="text-sm text-[var(--color-ink)] font-medium">Tracking: {{ $label['tracking_number'] }}</p>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <h3 class="text-xs font-medium text-[var(--color-steel)] uppercase mb-2">From</h3>
                <p class="text-sm text-[var(--color-ink)]"></p>
                <p class="text-sm text-[var(--color-steel)]"></p>
                <p class="text-sm text-[var(--color-steel)]"></p>
            </div>
            <div>
                <h3 class="text-xs font-medium text-[var(--color-steel)] uppercase mb-2">To</h3>
                <p class="text-sm text-[var(--color-ink)]">{{ $label['recipient'] }}</p>
                <p class="text-sm text-[var(--color-steel)]">{{ $label['address'] }}</p>
                <p class="text-sm text-[var(--color-steel)]">{{ $label['city'] }}, {{ $label['state'] }} {{ $label['postal_code'] }}</p>
                @if($label['phone'])
                    <p class="text-sm text-[var(--color-steel)]">Phone: {{ $label['phone'] }}</p>
                @endif
            </div>
        </div>

        <div class="mt-8 pt-4 border-t hairline">
            <div class="flex items-center justify-between text-sm">
                <div>
                    <span class="text-[var(--color-steel)]">Weight:</span>
                    <span class="text-[var(--color-ink)] font-medium">{{ number_format($label['weight'], 2) }} kg</span>
                </div>
                <div>
                    <span class="text-[var(--color-steel)]">Order:</span>
                    <span class="text-[var(--color-ink)] font-medium">{{ $label['order_number'] }}</span>
                </div>
            </div>
        </div>

        @if(count($label['items']) > 0)
        <div class="mt-6 pt-4 border-t hairline">
            <h3 class="text-xs font-medium text-[var(--color-steel)] uppercase mb-2">Contents</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[var(--color-steel)]">
                        <th class="text-left pb-2">Item</th>
                        <th class="text-left pb-2">SKU</th>
                        <th class="text-right pb-2">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($label['items'] as $item)
                        <tr>
                            <td class="py-2 text-[var(--color-ink)]">{{ $item['name'] }} {{ $item['variant'] ? '(' . $item['variant'] . ')' : '' }}</td>
                            <td class="py-2 text-[var(--color-steel)]">{{ $item['sku'] }}</td>
                            <td class="py-2 text-[var(--color-ink)] text-right">{{ $item['quantity'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
