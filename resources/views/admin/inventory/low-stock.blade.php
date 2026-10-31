@extends('layouts.admin')

@section('title', 'Low Stock Products')

@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Low Stock Products</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            @if($lowStockThreshold === null)
                No low-stock threshold is configured, so nothing can be reported. Set one in
                <a href="{{ route('admin.settings.index') }}" class="text-[var(--color-red)] hover:underline">Settings &rarr; Order &amp; Inventory</a>.
            @else
                Items at or below the reorder threshold ({{ $lowStockThreshold }} units)
            @endif
        </p>
    </div>
    <a href="{{ route('admin.inventory.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Inventory</a>
</div>
@endsection

@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Product</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Variant</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-28">Qty</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-32">Reorder Level</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase w-40">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($products ?? [] as $item)
                    @php
                        $stockLevel = $item->quantity;
                        $threshold = $item->reorder_level ?? $lowStockThreshold ?? 5;
                        $urgency = $stockLevel <= 2 ? 'critical' : ($stockLevel <= $threshold ? 'high' : 'medium');
                        $urgencyClass = $urgency === 'critical' ? 'bg-[var(--color-red)]' : ($urgency === 'high' ? 'bg-[var(--color-amber)]' : 'bg-[var(--color-paper-dim)]');
                        $urgencyText = $urgency === 'critical' ? 'CRITICAL' : ($urgency === 'high' ? 'HIGH' : 'MEDIUM');
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4">
                            <div class="font-medium text-[var(--color-ink)]">{{ $item->product->name ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $item->variant->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full {{ $urgencyClass }}/10 text-[var(--color-red)]">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                {{ $item->quantity }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $threshold }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.inventory.show', $item->getKey()) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-[var(--color-red)] text-white text-sm font-medium rounded-lg hover:bg-[var(--color-red-dark)] transition">
                                <i class="fa-solid fa-boxes-stacked"></i> Adjust Stock
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <i class="fa-solid fa-check-circle text-4xl text-[#1B7A43]/50 mb-3 block"></i>
                            <p class="text-[var(--color-steel)]">All products are adequately stocked.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
