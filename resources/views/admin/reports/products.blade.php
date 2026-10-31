@extends('layouts.admin')

@section('title', 'Products Report')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Products Report</h1>
    <a href="{{ route('admin.reports.index') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Back to Reports</a>
</div>
@endsection

@section('content')
<form method="GET" class="bg-[var(--color-paper)] rounded-lg border p-4 mb-6 flex flex-wrap gap-4 items-end">
    <div>
        <label class="block text-xs font-medium text-[var(--color-steel)] mb-1">Sort By</label>
        <select name="sort_by" class="border rounded-lg px-3 py-2">
            <option value="sales" {{ ($filters['sort_by'] ?? '') === 'sales' ? 'selected' : '' }}>Sales</option>
            <option value="revenue" {{ ($filters['sort_by'] ?? '') === 'revenue' ? 'selected' : '' }}>Revenue</option>
            <option value="quantity" {{ ($filters['sort_by'] ?? '') === 'quantity' ? 'selected' : '' }}>Quantity</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-[var(--color-steel)] mb-1">Order</label>
        <select name="sort_order" class="border rounded-lg px-3 py-2">
            <option value="desc" {{ ($filters['sort_order'] ?? '') === 'desc' ? 'selected' : '' }}>Desc</option>
            <option value="asc" {{ ($filters['sort_order'] ?? '') === 'asc' ? 'selected' : '' }}>Asc</option>
        </select>
    </div>
    <button class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm">Apply</button>
    <a href="{{ route('admin.reports.export', ['report_type' => 'products']) }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Export CSV</a>
</form>

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Products</p><p class="text-xl font-bold">{{ number_format($productsReport['total_products'] ?? 0) }}</p></div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Active Products</p><p class="text-xl font-bold">{{ number_format($productsReport['active_products'] ?? 0) }}</p></div>
</div>
@endsection
