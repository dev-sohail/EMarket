@extends('layouts.admin')

@section('title', 'Shipping Zones')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Shipping Zones</h1>
    <div class="flex gap-2">
        <a href="{{ route('admin.shipping.methods.index') }}" class="bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-line)]">Methods</a>
        <a href="{{ route('admin.shipping.zones.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)]">Add Zone</a>
    </div>
</div>
@endsection

@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Cities</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($zones ?? [] as $zone)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">{{ $zone->name }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $zone->cities->pluck('city')->implode(', ') ?: '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $zone->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]' }}">{{ $zone->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.shipping.zones.edit', $zone->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <a href="{{ route('admin.shipping.rules.index', $zone->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Rules</a>
                                <form method="POST" action="{{ route('admin.shipping.zones.destroy', $zone->getKey()) }}" data-confirm="Delete this zone?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-[var(--color-steel)]">No shipping zones found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
