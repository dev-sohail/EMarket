@extends('layouts.admin')
@section('title', 'Reports')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Reports</h1>
    <div class="flex items-center gap-2">
        <select id="exportReportType" class="border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-indigo-500">
            <option value="sales">Sales Report</option>
            <option value="orders">Orders Report</option>
            <option value="products">Products Report</option>
            <option value="inventory">Inventory Report</option>
            <option value="customers">Customers Report</option>
            <option value="returns">Returns Report</option>
            <option value="coupons">Coupons Report</option>
        </select>
        <button type="button" data-action="exportReport" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)]">
            Export
        </button>
    </div>
</div>
@endsection
@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <p class="text-sm text-[var(--color-steel)]">Total Revenue</p>
        <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ format_currency($totalSales ?? 0) }}</p>
        <p class="text-xs text-[var(--color-steel)] mt-1">All time</p>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <p class="text-sm text-[var(--color-steel)]">This Month</p>
        <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ format_currency($monthlySales ?? 0) }}</p>
        <p class="text-xs text-[var(--color-steel)] mt-1">{{ now()->format('F Y') }}</p>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <p class="text-sm text-[var(--color-steel)]">Total Orders</p>
        <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ number_format($totalOrders ?? 0) }}</p>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <p class="text-sm text-[var(--color-steel)]">Avg. Order Value</p>
        <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ format_currency($avgOrderValue ?? 0) }}</p>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h3 class="font-semibold text-[var(--color-ink)] mb-4">Sales by Period</h3>
        <div class="h-64">
            <canvas id="reportSalesChart"></canvas>
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h3 class="font-semibold text-[var(--color-ink)] mb-4">Top Products</h3>
        <div class="space-y-3">
            @forelse($topProducts ?? [] as $product)
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-[var(--color-ink)]">{{ $product->name }}</span>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium">{{ $product->total_sold ?? 0 }} sold</p>
                        <p class="text-xs text-[var(--color-steel)]">{{ format_currency($product->base_price * ($product->total_sold ?? 0)) }}</p>
                    </div>
                </div>
            @empty
                <p class="text-center text-[var(--color-steel)] text-sm py-4">No data available.</p>
            @endforelse
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h3 class="font-semibold text-[var(--color-ink)] mb-4">Sales by Category</h3>
        <div class="space-y-3">
            @forelse($categorySales ?? [] as $cat)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-[var(--color-ink)]">{{ $cat->name }}</span>
                    <span class="text-sm font-medium">{{ format_currency($cat->total_revenue ?? $cat->total_quantity ?? 0) }}</span>
                </div>
            @empty
                <p class="text-center text-[var(--color-steel)] text-sm py-4">No data available.</p>
            @endforelse
        </div>
    </div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h3 class="font-semibold text-[var(--color-ink)] mb-4">Recent Orders</h3>
        <div class="space-y-3">
            @forelse($recentOrders ?? [] as $order)
                <div class="flex items-center justify-between">
                    <div><p class="text-sm font-medium text-[var(--color-ink)]">#{{ $order->order_number }}</p><p class="text-xs text-[var(--color-steel)]">{{ $order->user->name ?? 'Guest' }}</p></div>
                    <span class="text-sm font-medium">{{ format_currency($order->grand_total) }}</span>
                </div>
            @empty
                <p class="text-center text-[var(--color-steel)] text-sm py-4">No recent orders.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
function exportReport() {
    var type = document.getElementById('exportReportType').value;
    window.location.href = '{{ route("admin.reports.export") }}?report_type=' + type + '&format=csv';
}
document.addEventListener('DOMContentLoaded', function() {
    var currencySymbol = '{{ currency_symbol() }}';
    var ctx = document.getElementById('reportSalesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($monthlyLabels ?? []),
                datasets: [{
                    label: 'Monthly Sales',
                    data: @json($monthlyValues ?? []),
                    backgroundColor: '#6366F1',
                    borderColor: '#4F46E5',
                    borderWidth: 1
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
});
</script>
@endpush