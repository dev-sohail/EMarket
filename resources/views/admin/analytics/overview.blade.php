@extends('layouts.admin')

@section('title', 'Analytics Overview')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Analytics Overview</h1>
        <p class="text-[var(--color-steel)] mt-1">Sales, orders, customers, and visitor insights</p>
    </div>
    <div class="flex items-center gap-3">
        <form method="GET" class="flex items-center gap-2">
            <select name="range" data-auto-submit aria-label="Reporting period"
                    class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="7" @selected($range == 7)>Last 7 days</option>
                <option value="30" @selected($range == 30)>Last 30 days</option>
                <option value="90" @selected($range == 90)>Last 90 days</option>
                <option value="365" @selected($range == 365)>Last 365 days</option>
            </select>
        </form>
        <a href="{{ route('admin.analytics.export', ['report_type' => 'overview']) }}"
           class="px-4 py-2 bg-[var(--color-red)] text-white rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition-colors inline-flex items-center">
            <i class="fa-solid fa-download mr-2"></i>Export
        </a>
    </div>
</div>
@endsection

@section('content')
@include('admin.analytics._nav')

@php
    // Each card states what it measures and how it moved. The previous version
    // hard-coded a "$" prefix while the rest of the app formats with
    // format_currency(), so a non-USD store showed the wrong symbol here.
    $kpis = [
        [
            'label' => 'Total Revenue',
            'value' => format_currency((float) $totalRevenue),
            'change' => $revenueChange,
            'icon' => 'fa-chart-line',
            'href' => route('admin.analytics.overview', ['range' => $range]),
        ],
        [
            'label' => 'Total Orders',
            'value' => number_format($totalOrders),
            'change' => $ordersChange,
            'icon' => 'fa-box-archive',
            'href' => route('admin.orders.index'),
            'link_label' => 'View orders',
        ],
        [
            'label' => 'Avg Order Value',
            'value' => format_currency((float) $avgOrderValue),
            'change' => $aovChange,
            'icon' => 'fa-calculator',
            'href' => route('admin.analytics.overview', ['range' => $range]),
        ],
        [
            'label' => 'Total Visitors',
            'value' => number_format($totalVisitors),
            'change' => $visitorChange,
            'icon' => 'fa-users',
            'href' => route('admin.analytics.visitors', ['range' => $range]),
        ],
        [
            'label' => 'New Customers',
            'value' => number_format($totalCustomers),
            'change' => $customersChange,
            'icon' => 'fa-user-plus',
            'href' => route('admin.analytics.customers', ['range' => $range]),
        ],
        [
            'label' => 'Conversion Rate',
            'value' => $conversionRate.'%',
            'change' => null,
            'icon' => 'fa-bullseye',
            'href' => route('admin.analytics.visitors', ['range' => $range]),
        ],
        [
            'label' => 'Low Stock Items',
            'value' => number_format($lowStockCount),
            'change' => null,
            'icon' => 'fa-exclamation-triangle',
            'href' => route('admin.inventory.low-stock'),
            'link_label' => 'Review stock',
            'alert' => $lowStockCount > 0,
        ],
        [
            'label' => 'Top Categories',
            'value' => (string) $topCategories->count(),
            'change' => null,
            'icon' => 'fa-tags',
            'href' => route('admin.analytics.products', ['range' => $range]),
        ],
    ];

    // Guard: a period with no paid orders leaves max() at 0, and dividing by it
    // raised DivisionByZeroError, taking the whole page to a 500.
    $categoryRevenueMax = (float) $topCategories->max('total_revenue');
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach($kpis as $kpi)
        @php
            $hasChange = $kpi['change'] !== null;
            $up = $hasChange && $kpi['change'] >= 0;
        @endphp
        <a href="{{ $kpi['href'] }}" class="block bg-[var(--color-paper)] rounded-lg border hairline p-5 transition-shadow hover:shadow-sm hover:border-[var(--color-steel)]">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-[var(--color-steel)]">{{ $kpi['label'] }}</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)] mt-1 truncate">{{ $kpi['value'] }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ ($kpi['alert'] ?? false) ? 'bg-[var(--color-red)]/10' : 'bg-[var(--color-paper-dim)]' }}">
                    <i class="fa-solid {{ $kpi['icon'] }} {{ ($kpi['alert'] ?? false) ? 'text-[var(--color-red)]' : 'text-[var(--color-ink-soft)]' }}"></i>
                </div>
            </div>

            @if($hasChange)
                <p class="text-xs mt-2 inline-flex items-center gap-1 {{ $up ? 'text-[#1B7A43]' : 'text-[var(--color-red)]' }}">
                    <i class="fa-solid {{ $up ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} text-[10px]"></i>
                    {{ number_format(abs($kpi['change']), 1) }}% <span class="text-[var(--color-steel)]">vs previous period</span>
                </p>
            @else
                <p class="text-xs mt-2 text-[var(--color-steel)]">
                    @if($kpi['label'] === 'Conversion Rate')
                        Visitors who placed an order
                    @elseif($kpi['label'] === 'Low Stock Items')
                        At or below the low-stock threshold
                    @else
                        Ranked by revenue
                    @endif
                </p>
            @endif
        </a>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 lg:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Revenue &amp; Orders Trend</h2>
            <p class="text-xs text-[var(--color-steel)]">
                {{ $startDate?->format('M d, Y') }} – {{ $endDate?->format('M d, Y') }}
            </p>
        </div>
        <div style="height: 320px; width: 100%;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Top Categories</h2>
        @forelse($topCategories as $category)
            @php $share = $categoryRevenueMax > 0 ? ($category['total_revenue'] / $categoryRevenueMax) * 100 : 0; @endphp
            <div class="mb-4 last:mb-0">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium text-[var(--color-ink)] truncate">{{ $category['name'] }}</p>
                    <p class="text-sm font-semibold text-[var(--color-ink)] whitespace-nowrap">{{ format_currency((float) $category['total_revenue']) }}</p>
                </div>
                <div class="w-full bg-[var(--color-paper-dim)] rounded-full h-2 mt-1.5">
                    <div class="bg-[var(--color-red)] h-2 rounded-full" style="width: {{ number_format($share, 1) }}%"></div>
                </div>
                <p class="text-xs text-[var(--color-steel)] mt-1">{{ number_format($category['total_quantity']) }} sold</p>
            </div>
        @empty
            <div class="py-8 text-center">
                <div class="w-10 h-10 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-2">
                    <i class="fa-solid fa-tags text-[var(--color-steel)]"></i>
                </div>
                <p class="text-sm text-[var(--color-steel)]">No category sales in this period.</p>
            </div>
        @endforelse
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Top Products</h2>
            <a href="{{ route('admin.analytics.products', ['range' => $range]) }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b hairline">
                        <th class="pb-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Product</th>
                        <th class="pb-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider text-right">Sold</th>
                        <th class="pb-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topProducts as $product)
                        <tr class="border-b hairline last:border-0 hover:bg-[var(--color-paper-dim)] transition-colors">
                            <td class="py-3">
                                <div class="flex items-center gap-3">
                                    @if($product['image'])
                                        <img src="{{ asset('storage/' . $product['image']) }}" alt="" class="w-10 h-10 rounded object-cover flex-shrink-0">
                                    @else
                                        <div class="w-10 h-10 rounded bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-box text-[var(--color-steel)]"></i>
                                        </div>
                                    @endif
                                    <span class="font-medium text-[var(--color-ink)]">{{ $product['name'] }}</span>
                                </div>
                            </td>
                            <td class="py-3 text-right text-[var(--color-steel)]">{{ number_format($product['total_sold']) }}</td>
                            <td class="py-3 text-right font-medium text-[var(--color-ink)]">{{ format_currency((float) $product['total_revenue']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-8 text-center text-[var(--color-steel)]">No products sold in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Recent Orders</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">View all</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b hairline">
                        <th class="pb-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Order</th>
                        <th class="pb-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Customer</th>
                        <th class="pb-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Total</th>
                        <th class="pb-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                        <tr class="border-b hairline last:border-0 hover:bg-[var(--color-paper-dim)] transition-colors">
                            <td class="py-3">
                                <a href="{{ route('admin.orders.show', $order->getKey()) }}" class="font-medium font-mono text-[var(--color-red)] hover:text-[var(--color-red-dark)]">{{ $order->order_number }}</a>
                            </td>
                            <td class="py-3 text-[var(--color-ink-soft)]">{{ $order->customer?->full_name ?? 'Guest' }}</td>
                            <td class="py-3 text-right font-medium text-[var(--color-ink)]">{{ format_currency((float) $order->grand_total) }}</td>
                            <td class="py-3">
                                <span class="px-2 py-1 text-xs rounded bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]">{{ $order->status?->label() ?? 'Unknown' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-[var(--color-steel)]">No orders yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('revenueChart');
    if (!canvas) return;

    const style = getComputedStyle(document.documentElement);
    const grid = (name, fallback) => (style.getPropertyValue(name) || fallback).trim();

    const ink = grid('--color-ink', '#14171a');
    const steel = grid('--color-steel', '#6b6f76');
    const red = grid('--color-red', '#d6001c');
    const line = grid('--color-line', '#dedcd4');

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: @json($labels ?? []),
            datasets: [
                {
                    label: 'Revenue',
                    data: @json($revenueData ?? []),
                    borderColor: red,
                    backgroundColor: 'rgba(214, 0, 28, 0.08)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0.3,
                    fill: true,
                    yAxisID: 'y',
                },
                {
                    label: 'Orders',
                    data: @json($ordersData ?? []),
                    borderColor: ink,
                    backgroundColor: 'rgba(20, 23, 26, 0.04)',
                    borderWidth: 2,
                    borderDash: [4, 4],
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0.3,
                    fill: true,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    grid: { color: line, drawBorder: false },
                    border: { display: false },
                    ticks: { color: steel, maxTicksLimit: 8 },
                    beginAtZero: true,
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: steel, maxTicksLimit: 8, precision: 0 },
                    beginAtZero: true,
                },
                x: {
                    grid: { display: false },
                    border: { color: line },
                    ticks: { color: steel, maxTicksLimit: 12, maxRotation: 0, autoSkipPadding: 16 },
                },
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { color: steel, usePointStyle: true, pointStyle: 'line', boxWidth: 24 },
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ctx.dataset.yAxisID === 'y'
                            ? ` ${ctx.dataset.label}: ${formatCurrencyForChart(ctx.parsed.y)}`
                            : ` ${ctx.dataset.label}: ${ctx.parsed.y}`,
                    },
                },
            },
        },
    });

    function formatCurrencyForChart(value) {
        try {
            return new Intl.NumberFormat(document.documentElement.lang || 'en', {
                style: 'currency',
                currency: @json(setting('currency', 'USD')),
            }).format(value);
        } catch {
            return value;
        }
    }
});
</script>
@endpush
@endsection