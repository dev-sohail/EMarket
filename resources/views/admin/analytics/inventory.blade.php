@extends('layouts.admin')

@section('title', 'Inventory Analytics')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Inventory Analytics</h1>
        <p class="text-[var(--color-steel)] mt-1">Stock levels and product turnover</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <select name="range" data-auto-submit aria-label="Reporting period" class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="7" @selected($range == 7)>Last 7 days</option>
            <option value="30" @selected($range == 30)>Last 30 days</option>
            <option value="90" @selected($range == 90)>Last 90 days</option>
            <option value="365" @selected($range == 365)>Last 365 days</option>
        </select>
        <a href="{{ route('admin.inventory.low-stock') }}"
           class="px-4 py-2 bg-[var(--color-red)] text-white rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition-colors inline-flex items-center">
            <i class="fa-solid fa-warehouse mr-2"></i>Manage Stock
        </a>
    </form>
</div>
@endsection

@section('content')
@include('admin.analytics._nav')

@php
    // Turnover is only meaningful once something has sold, so an unsold product
    // is shown as "no movement" rather than 0.00x sitting next to real rates.
    $turnoverMax = (float) ($stockTurnover->max('turnover_rate') ?? 0);

    $inventoryStats = [
        [
            'label' => 'Out of Stock',
            'value' => number_format($outOfStockCount),
            'icon' => 'fa-circle-xmark',
            'note' => 'Zero units on hand',
            'tone' => 'danger',
        ],
        [
            'label' => 'Low Stock Alerts',
            'value' => number_format(count($lowStockItems)),
            'icon' => 'fa-triangle-exclamation',
            'note' => 'At or below reorder point',
            'tone' => count($lowStockItems) > 0 ? 'warning' : 'neutral',
        ],
        [
            'label' => 'Fast Moving',
            'value' => number_format(count($fastMovingProducts)),
            'icon' => 'fa-arrow-trend-up',
            'note' => 'Highest unit sales',
            'tone' => 'success',
        ],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    @foreach($inventoryStats as $stat)
        @php
            $toneClasses = match ($stat['tone']) {
                'danger' => ['value' => 'text-[var(--color-red)]', 'icon' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]'],
                'warning' => ['value' => 'text-[#B45309]', 'icon' => 'bg-[#F59E0B]/10 text-[#B45309]'],
                'success' => ['value' => 'text-[#1B7A43]', 'icon' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
                default => ['value' => 'text-[var(--color-ink)]', 'icon' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
            };
        @endphp
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-[var(--color-steel)]">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold {{ $toneClasses['value'] }} mt-1">{{ $stat['value'] }}</p>
                    <p class="text-xs text-[var(--color-steel)] mt-2">{{ $stat['note'] }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $toneClasses['icon'] }}">
                    <i class="fa-solid {{ $stat['icon'] }}"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden self-start">
        <div class="p-4 border-b hairline flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Low Stock Items</h2>
            @if(count($lowStockItems) > 0)
                <a href="{{ route('admin.inventory.low-stock') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Restock</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Product</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">SKU</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Quantity</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Reorder At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-line)]">
                    @forelse($lowStockItems as $item)
                        @php $isOut = (int) $item['quantity'] <= 0; @endphp
                        <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                            <td class="px-4 py-3 font-medium text-[var(--color-ink)]">{{ $item['product_name'] }}</td>
                            <td class="px-4 py-3 text-[var(--color-steel)] font-mono text-xs">{{ $item['sku'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $isOut ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : 'bg-[#F59E0B]/10 text-[#B45309]' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $isOut ? 'bg-[var(--color-red)]' : 'bg-[#F59E0B]' }}"></span>
                                    {{ number_format($item['quantity']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-[var(--color-steel)]">{{ $item['threshold'] === null ? '—' : number_format($item['threshold']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center">
                                <div class="w-12 h-12 mx-auto rounded-full bg-[#1B7A43]/10 flex items-center justify-center mb-3">
                                    <i class="fa-solid fa-circle-check text-[#1B7A43]"></i>
                                </div>
                                <p class="text-sm font-medium text-[var(--color-ink)]">Stock levels are healthy</p>
                                <p class="text-sm text-[var(--color-steel)] mt-1">Nothing is at or below its reorder point.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden self-start">
        <div class="p-4 border-b hairline flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Stock Turnover Rate</h2>
            <span class="text-xs text-[var(--color-steel)]">Units sold ÷ average stock</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Product</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Sold</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Avg Stock</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Turnover</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-line)]">
                    @forelse($stockTurnover as $item)
                        @php $rate = (float) $item->turnover_rate; @endphp
                        <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                            <td class="px-4 py-3 font-medium text-[var(--color-ink)]">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-right text-[var(--color-ink-soft)]">{{ number_format($item->sold) }}</td>
                            <td class="px-4 py-3 text-right text-[var(--color-steel)]">{{ number_format($item->avg_stock, 0) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($rate > 0)
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-16 bg-[var(--color-paper-dim)] rounded-full h-1.5">
                                            <div class="bg-[var(--color-red)] h-1.5 rounded-full" style="width: {{ $turnoverMax > 0 ? number_format(($rate / $turnoverMax) * 100, 1) : 0 }}%"></div>
                                        </div>
                                        <span class="font-medium text-[var(--color-ink)] w-12 text-right">{{ number_format($rate, 2) }}x</span>
                                    </div>
                                @else
                                    <span class="text-xs text-[var(--color-steel)]">No movement</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center">
                                <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                    <i class="fa-solid fa-warehouse text-[var(--color-steel)]"></i>
                                </div>
                                <p class="text-sm font-medium text-[var(--color-ink)]">No turnover data yet</p>
                                <p class="text-sm text-[var(--color-steel)] mt-1">Turnover appears once products start selling.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection