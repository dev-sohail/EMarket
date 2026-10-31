@extends('layouts.admin')

@section('title', 'Sales Report')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Sales Report</h1>
    <a href="{{ route('admin.reports.index') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to Reports</a>
</div>
@endsection

@section('content')
<form method="GET" class="bg-[var(--color-paper)] rounded-lg border p-4 mb-6 flex flex-wrap gap-4 items-end">
    <div>
        <label class="block text-xs font-medium text-[var(--color-steel)] mb-1">From</label>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="border rounded-lg px-3 py-2">
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--color-steel)] mb-1">To</label>
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="border rounded-lg px-3 py-2">
    </div>
    <button class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm">Filter</button>
    <a href="{{ route('admin.reports.export', ['report_type' => 'sales', 'date_from' => $filters['date_from'] ?? '', 'date_to' => $filters['date_to'] ?? '']) }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Export CSV</a>
</form>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Sales</p><p class="text-xl font-bold">{{ format_currency($salesReport['total_sales'] ?? 0) }}</p></div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Orders</p><p class="text-xl font-bold">{{ number_format($salesReport['total_orders'] ?? 0) }}</p></div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Avg Order Value</p><p class="text-xl font-bold">{{ format_currency($salesReport['average_order_value'] ?? 0) }}</p></div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Tax</p><p class="text-xl font-bold">{{ format_currency($salesReport['total_tax'] ?? 0) }}</p></div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border p-6">
    <h2 class="font-semibold text-[var(--color-ink)] mb-4">Daily Sales</h2>
    <table class="w-full">
        <thead class="bg-[var(--color-paper-dim)]">
            <tr><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Date</th><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Orders</th><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Sales</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse($salesReport['daily_sales'] ?? [] as $day)
                <tr><td class="px-4 py-2 text-sm">{{ $day['date'] }}</td><td class="px-4 py-2 text-sm">{{ $day['orders'] }}</td><td class="px-4 py-2 text-sm">{{ format_currency($day['sales']) }}</td></tr>
            @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-[var(--color-steel)]">No data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
