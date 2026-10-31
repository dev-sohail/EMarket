@extends('layouts.admin')
@section('title', 'Customers')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Customers</h1>
    <a href="{{ route('admin.customers.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Add Customer
    </a>
</div>
@endsection
@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border p-4 mb-6">
    <form method="GET" class="flex flex-wrap items-center gap-4">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search customers..." class="flex-1 min-w-[200px] border rounded-lg px-4 py-2 text-sm focus:ring-1 focus:ring-indigo-500">
        <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-ink)]">Search</button>
    </form>
</div>
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Customer</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Phone</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Orders</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Total Spent</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Joined</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($customers ?? [] as $customer)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-[var(--color-paper-dim)] rounded-full flex items-center justify-center text-[var(--color-red)] font-medium text-sm">{{ substr($customer->first_name ?? 'U', 0, 1) }}</div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium text-[var(--color-ink)] text-sm">{{ $customer->first_name }} {{ $customer->last_name }}</p>
                                        @if(!$customer->user_id)
                                            <span class="inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-steel)]">Guest</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-[var(--color-steel)]">{{ $customer->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $customer->phone ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $customer->orders_count ?? 0 }}</td>
                        <td class="px-6 py-4 text-sm font-medium">{{ format_currency($customer->total_spent ?? 0) }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $customer->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.customers.show', $customer->getKey()) }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink-soft)] text-sm">View</a>
                                <a href="{{ route('admin.customers.edit', $customer->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                @if($customer->user_id)
                                    <form method="POST" action="{{ route('admin.customers.impersonate', $customer->getKey()) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm" data-confirm="Impersonate this customer?">Impersonate</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.customers.destroy', $customer->getKey()) }}" data-confirm="Delete this customer?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-[var(--color-steel)]">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(isset($customers) && method_exists($customers, 'links'))
        <div class="p-4 border-t">{{ $customers->links() }}</div>
    @endif
</div>
@endsection
