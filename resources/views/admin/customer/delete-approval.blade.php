@extends('layouts.admin')
@section('title', 'Delete Approval - Trashed Items')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Delete Approval</h1>
    <a href="{{ route('admin.customers.index') }}" class="bg-[var(--color-paper-dim)] text-[var(--color-ink)] px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-paper-dim)] transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Customers
    </a>
</div>
@endsection
@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border p-4 mb-6">
    <p class="text-sm text-[var(--color-steel)]">Review and permanently delete soft-deleted customers. This action cannot be undone.</p>
</div>
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Customer</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Email</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Phone</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Orders</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Deleted At</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($trashedCustomers ?? [] as $customer)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-[var(--color-red-light)] rounded-full flex items-center justify-center text-[var(--color-red)] font-medium text-sm">{{ substr($customer->first_name ?? 'U', 0, 1) }}</div>
                                <div>
                                    <p class="font-medium text-[var(--color-ink)] text-sm">{{ $customer->first_name }} {{ $customer->last_name }}</p>
                                    <p class="text-xs text-[var(--color-steel)]">ID: {{ $customer->getKey() }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $customer->email }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $customer->phone ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $customer->orders_count ?? 0 }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $customer->deleted_at->format('M d, Y H:i') }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.force-delete', $customer->getKey()) }}" data-confirm="WARNING: This will PERMANENTLY DELETE this customer and all associated data. This action cannot be undone. Are you absolutely sure?">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">Permanently Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-[var(--color-steel)]">No soft-deleted customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($trashedCustomers) && method_exists($trashedCustomers, 'links'))
        <div class="p-4 border-t">{{ $trashedCustomers->links() }}</div>
    @endif
</div>
@endsection