@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('header')
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Dashboard</h1>
        <p class="text-[var(--color-steel)] mt-1">Welcome back, {{ auth()->guard('admin')->user()?->name ?? 'Admin' }}</p>
    </div>
</div>
@endsection

@section('content')
{{-- Stats Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[var(--color-steel)]">Total Orders</p>
                <p class="text-2xl font-bold text-[var(--color-ink)] mt-1 price">{{ number_format($totalOrders ?? 0) }}</p>
            </div>
            <div class="w-12 h-12 bg-[var(--color-paper-dim)] rounded flex items-center justify-center">
                <svg class="w-6 h-6 text-[var(--color-ink-soft)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
        </div>
        @if(isset($stats['orders_today']) && $stats['orders_today'] > 0)
            <div class="flex items-center gap-1 mt-2">
                <span class="text-[#1B7A43] text-sm font-medium">
                    {{ $stats['orders_today'] }} today
                </span>
                <span class="text-[var(--color-steel)] text-sm">total orders</span>
            </div>
        @endif
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[var(--color-steel)]">Revenue</p>
                <p class="text-2xl font-bold text-[var(--color-ink)] mt-1 price">{{ format_currency($totalSales ?? 0) }}</p>
            </div>
            <div class="w-12 h-12 bg-[var(--color-paper-dim)] rounded flex items-center justify-center">
                <svg class="w-6 h-6 text-[var(--color-ink-soft)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        @if(isset($stats['revenue_today']) && $stats['revenue_today'] > 0)
            <div class="flex items-center gap-1 mt-2">
                <span class="text-[#1B7A43] text-sm font-medium">
                    {{ format_currency($stats['revenue_today']) }} today
                </span>
                <span class="text-[var(--color-steel)] text-sm">total revenue</span>
            </div>
        @endif
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[var(--color-steel)]">Products</p>
                <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ number_format($stats['total_products'] ?? 0) }}</p>
            </div>
            <div class="w-12 h-12 bg-[var(--color-paper-dim)] rounded flex items-center justify-center">
                <svg class="w-6 h-6 text-[var(--color-ink-soft)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        @if(isset($stats['in_stock_products']) && $stats['in_stock_products'] > 0)
            <div class="flex items-center gap-1 mt-2">
                <span class="text-[var(--color-sale)] text-sm font-medium">
                    {{ number_format($stats['in_stock_products'] ?? 0) }} in-stock
                </span>
                <span class="text-[var(--color-steel)] text-sm">
                    products
                </span>
            </div>
        @endif
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[var(--color-steel)]">Customers</p>
                <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ number_format($stats['total_customers'] ?? 0) }}</p>
            </div>
            <div class="w-12 h-12 bg-[var(--color-paper-dim)] rounded flex items-center justify-center">
                <svg class="w-6 h-6 text-[var(--color-ink-soft)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>
        @if(isset($stats['customers_with_orders']) && $stats['customers_with_orders'] > 0)
            <div class="flex items-center gap-1 mt-2">
                <span class="text-[var(--color-sale)] text-sm font-medium">
                    {{ number_format($stats['customers_with_orders'] ?? 0) }}
                </span>
                <span class="text-[var(--color-steel)] text-sm">
                    customers with orders
                </span>
            </div>
        @endif
    </div>
</div>

    @if(!empty($configWarnings))
        <div class="mb-8 space-y-3">
            @foreach($configWarnings as $warning)
                <div class="flex items-start gap-3 p-4 bg-[var(--color-red)]/10 border border-[var(--color-red)]/40 rounded-lg">
                    <svg class="w-5 h-5 text-[var(--color-red)] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-[var(--color-red-dark)]">{{ $warning['message'] }}</p>
                        <p class="text-xs text-[var(--color-steel)] mt-1">{{ $warning['action'] }}</p>
                    </div>
                    @if(!empty($warning['route']))
                            <a href="{{ route($warning['route']) }}" class="text-xs font-medium text-[var(--color-red)] hover:underline flex-shrink-0">Fix →</a>
                        @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-[var(--color-paper)] rounded-lg border hairline">
        <div class="p-6 border-b hairline flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Recent Orders</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table id="ordersTable" class="w-full display">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">#</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Customer</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Total</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-line)]">
                    @forelse($recentOrders ?? [] as $order)
                        <tr class="hover:bg-[var(--color-paper)]">
                            <td class="px-6 py-4">
                                <a href="{{ route('admin.orders.show', $order->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium text-sm">{{ $order->order_number }}</a>
                            </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            @if($order->customer)
                                {{ $order->customer->first_name }} {{ $order->customer->last_name }}
                            @elseif($order->user)
                                {{ $order->user->name }}
                            @else
                                Guest
                            @endif
                        </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                    {{ $order->status?->value === 'delivered' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : '' }}
                                    {{ $order->status?->value === 'processing' ? 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]' : '' }}
                                    {{ $order->status?->value === 'shipped' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : '' }}
                                    {{ $order->status?->value === 'pending' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : '' }}
                                    {{ $order->status?->value === 'cancelled' ? 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]' : '' }}">
                                    {{ $order->status?->label() ?? 'Unknown' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)] price">{{ format_currency($order->grand_total) }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $order->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-[var(--color-steel)]">No recent orders.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Low Stock Alerts --}}
    <div class="bg-[var(--color-paper)] rounded-lg border hairline">
        <div class="p-6 border-b hairline flex items-center justify-between">
            <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Low Stock Alerts</h2>
            @if($lowStockThreshold === null || $lowStockThreshold === '')
                <span class="text-xs text-[var(--color-steel)]">Threshold not set</span>
            @endif
        </div>
        <div class="divide-y divide-[var(--color-line)] max-h-80 overflow-y-auto">
            @if($lowStockThreshold === null || $lowStockThreshold === '')
                <div class="p-6 text-center text-[var(--color-steel)] text-sm">
                    Low stock threshold is not configured.
                    <a href="{{ route('admin.settings.index') }}" class="text-[var(--color-red)] hover:underline">Set it in Settings → Order &amp; Inventory.</a>
                </div>
            @else
                @forelse($lowStockProducts ?? [] as $product)
                    <div class="p-4 flex items-center gap-3">
                        <div class="w-10 h-10 bg-[var(--color-paper-dim)] rounded flex-shrink-0 flex items-center justify-center">
                            <svg class="w-5 h-5 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[var(--color-ink)] truncate">{{ $product->product?->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-[var(--color-red)]">Qty: {{ $product->quantity }} (min: {{ $product->reorder_level }})</p>
                        </div>
                        <a href="{{ route('admin.products.edit', $product->product?->getKey() ?? $product->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-xs">Edit</a>
                    </div>
                @empty
                    <div class="p-6 text-center text-[var(--color-steel)] text-sm">All products are well stocked.</div>
                @endforelse
            @endif
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h3 class="font-display font-semibold text-[var(--color-ink)] mb-4">Sales Overview</h3>
        <div class="h-64">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h3 class="font-display font-semibold text-[var(--color-ink)] mb-4">Orders by Status</h3>
        <div class="h-64">
            @if(empty($recentOrdersByStatus ?? []))
                <div class="flex items-center justify-center h-full text-[var(--color-steel)] text-sm">No data available.</div>
            @endif
            <canvas id="ordersChart"></canvas>
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h3 class="font-display font-semibold text-[var(--color-ink)] mb-4">Top Selling Products</h3>
        <div class="space-y-4">
            @forelse($topProducts ?? [] as $product)
                <div class="flex items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-[var(--color-ink)] truncate">{{ $product->name }}</p>
                        <p class="text-xs text-[var(--color-steel)]">{{ number_format($product->total_sold ?? 0) }} units sold</p>
                    </div>
                        <div class="w-16 h-6 bg-[var(--color-paper-dim)] rounded-full overflow-hidden">
                            <div class="h-full bg-[var(--color-red)]" style="width: {{ $product->total_sold > 0 ? min(100, ($product->total_sold / ($topProducts->first()?->total_sold ?? 1) * 100)) : 0 }}%"></div>
                        </div>
                </div>
            @empty
                <div class="text-center text-[var(--color-steel)] text-sm py-4">No data available.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    var currencySymbol = '{{ currency_symbol() }}';
    var salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: @json($monthlyLabels ?? []),
                datasets: [{
                    label: 'Sales',
                    data: @json($monthlyValues ?? []),
                    borderColor: '#d6001c',
                    backgroundColor: 'rgba(214, 0, 28, 0.08)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                aspectRatio: 2,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return currencySymbol + ' ' + v; } } } }
            }
        });
    }

    var ordersByStatus = @json($recentOrdersByStatus ?? []);
    var ordersCtx = document.getElementById('ordersChart');
    if (ordersCtx && Object.keys(ordersByStatus).length > 0) {
        // Use CSS variables for theme-aware colors
        var chartColors = [
            getComputedStyle(document.documentElement).getPropertyValue('--color-red').trim(),
            getComputedStyle(document.documentElement).getPropertyValue('--color-ink').trim(),
            getComputedStyle(document.documentElement).getPropertyValue('--color-steel').trim(),
            getComputedStyle(document.documentElement).getPropertyValue('--color-paper-dim').trim(),
            getComputedStyle(document.documentElement).getPropertyValue('--color-ink-soft').trim(),
            getComputedStyle(document.documentElement).getPropertyValue('--color-red').trim()
        ];
        new Chart(ordersCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(ordersByStatus),
                datasets: [{
                    data: Object.values(ordersByStatus),
                    backgroundColor: chartColors
                }]
            },
            options: { responsive: true, maintainAspectRatio: false,
                aspectRatio: 2,
                plugins: { legend: { display: false } }
            }
        });
    }

    if (window.jQuery && window.jQuery.fn.DataTable) {
        const $orders = window.jQuery('#ordersTable');

        // DataTables derives the column count from <thead> but reads row data
        // cell by cell, so it does not honour colspan. The empty state is a
        // single <td colspan="5">; enhancing it makes DataTables ask for cell 1
        // of a one-cell row and log
        // "Requested unknown parameter '1' for row 0, column 1".
        // Nothing is gained by enhancing an empty table - ordering, paging,
        // searching and the info line are all disabled here - so skip it.
        if ($orders.find('tbody td[colspan]').length === 0) {
            $orders.DataTable({
                ordering: false,
                info: false,
                paging: false,
                searching: false,
                responsive: true
            });
        }
    }
});
</script>
@endpush
