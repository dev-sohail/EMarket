@extends('layouts.admin')

@section('title', 'Returns Report')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Returns Report</h1>
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
    <div>
        <label class="block text-xs font-medium text-[var(--color-steel)] mb-1">Status</label>
        <select name="status" class="border rounded-lg px-3 py-2">
            <option value="">All</option>
            @foreach(['pending','approved','rejected','completed'] as $s)
                <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <button class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm">Filter</button>
    <a href="{{ route('admin.reports.export', ['report_type' => 'returns', 'date_from' => $filters['date_from'] ?? '', 'date_to' => $filters['date_to'] ?? '', 'status' => $filters['status'] ?? '']) }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Export CSV</a>
</form>

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Returns</p><p class="text-xl font-bold">{{ number_format($returnsReport['total_returns'] ?? 0) }}</p></div>
    <div class="bg-[var(--color-paper)] rounded-lg border p-5"><p class="text-sm text-[var(--color-steel)]">Total Returned Items</p><p class="text-xl font-bold">{{ number_format($returnsReport['total_returned_items'] ?? 0) }}</p></div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border p-6">
    <h2 class="font-semibold text-[var(--color-ink)] mb-4">By Status</h2>
    <table class="w-full">
        <thead class="bg-[var(--color-paper-dim)]">
            <tr><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th><th class="text-left px-4 py-2 text-xs font-medium text-[var(--color-steel)] uppercase">Count</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse($returnsReport['by_status'] ?? [] as $row)
                <tr><td class="px-4 py-2 text-sm">{{ ucfirst($row['status']) }}</td><td class="px-4 py-2 text-sm">{{ $row['count'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="px-4 py-6 text-center text-[var(--color-steel)]">No data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
