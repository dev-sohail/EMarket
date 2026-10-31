@extends('layouts.admin')

@section('title', 'Shipping Methods')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Shipping Methods</h1>
    <div class="flex gap-2">
        <a href="{{ route('admin.shipping.zones.index') }}" class="bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-line)]">Zones</a>
    </div>
</div>
@endsection

@section('content')
<form id="method-form" method="POST" action="{{ route('admin.shipping.methods.store') }}" class="bg-[var(--color-paper)] rounded-lg border p-6 mb-6 max-w-2xl space-y-4">
    @csrf
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Name *</label>
            <input type="text" name="name" id="method-name" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Base Cost ({{ currency_symbol() }}) *</label>
            <input type="number" step="0.01" name="base_cost" id="method-base_cost" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Per KG Cost ({{ currency_symbol() }})</label>
            <input type="number" step="0.01" name="per_kg_cost" id="method-per_kg_cost" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Estimated Days</label>
            <input type="number" name="estimated_days" id="method-estimated_days" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Sort Order</label>
            <input type="number" name="sort_order" id="method-sort_order" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Description</label>
        <textarea name="description" id="method-description" rows="3" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500"></textarea>
    </div>
    <label class="flex items-center gap-2"><input type="checkbox" name="is_active" id="method-is_active" value="1" checked class="rounded border-[var(--color-line)] text-[var(--color-red)]"><span class="text-sm">Active</span></label>
    <div class="flex gap-3">
        <button type="submit" class="bg-[var(--color-red)] text-white py-2.5 px-6 rounded-lg font-medium hover:bg-[var(--color-red-dark)]">Save Method</button>
    </div>
</form>

<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Rules</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($methods ?? [] as $method)
                    <tr id="method-{{ $method->getKey() }}" class="hover:bg-[var(--color-paper-dim)] scroll-mt-6">
                        <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">{{ $method->name }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $method->rules->count() }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $method->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]' }}">{{ $method->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.shipping.methods.edit', $method->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.shipping.methods.destroy', $method->getKey()) }}" data-confirm="Delete this method?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-[var(--color-steel)]">No shipping methods found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>


@endsection