@extends('layouts.admin')

@section('title', 'Inventory Report')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Inventory Report</h1>
    <a href="{{ route('admin.reports.index') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to Reports</a>
</div>
@endsection

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Items</p><p class="text-xl font-bold">{{ number_format($inventoryReport['total_items'] ?? 0) }}</p></div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Stock</p><p class="text-xl font-bold">{{ number_format($inventoryReport['total_stock'] ?? 0) }}</p></div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Reserved</p><p class="text-xl font-bold">{{ number_format($inventoryReport['total_reserved'] ?? 0) }}</p></div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Out of Stock</p><p class="text-xl font-bold">{{ count($inventoryReport['out_of_stock'] ?? []) }}</p></div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border p-6 mb-6">
    <h2 class="font-semibold text-[var(--color-ink)] mb-4">Low Stock Items</h2>
    <table class="w-full">
        <thead class="bg-[var(--color-paper-dim)]">
            <tr><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Product</th><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Variant</th><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Quantity</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse($inventoryReport['low_stock_items'] ?? [] as $item)
                <tr><td class="px-4 py-2 text-sm">{{ $item->product->name ?? '-' }}</td><td class="px-4 py-2 text-sm">{{ $item->variant->name ?? '-' }}</td><td class="px-4 py-2 text-sm">{{ $item->quantity }}</td></tr>
            @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-[var(--color-steel)]">
                    @if(($inventoryReport['threshold'] ?? null) === null)
                        No low-stock threshold is configured, so nothing can be reported.
                    @else
                        No low stock items.
                    @endif
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border p-6">
    <h2 class="font-semibold text-[var(--color-ink)] mb-4">Out of Stock Items</h2>
    <table class="w-full">
        <thead class="bg-[var(--color-paper-dim)]">
            <tr><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Product</th><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Variant</th><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Quantity</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse($inventoryReport['out_of_stock'] ?? [] as $item)
                <tr><td class="px-4 py-2 text-sm">{{ $item->product->name ?? '-' }}</td><td class="px-4 py-2 text-sm">{{ $item->variant->name ?? '-' }}</td><td class="px-4 py-2 text-sm">{{ $item->quantity }}</td></tr>
            @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-[var(--color-steel)]">No out of stock items.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="mt-4">
    <a href="{{ route('admin.reports.export', ['report_type' => 'inventory']) }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Export CSV</a>
</p>
@endsection
