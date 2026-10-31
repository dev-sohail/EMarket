@extends('layouts.admin')

@section('title', 'Attributes')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Product Attributes</h1>
        <p class="text-[var(--color-steel)] mt-1">Define product attributes for filtering, variants, and specifications</p>
    </div>
    <a href="{{ route('admin.attributes.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Add Attribute
    </a>
</div>
@endsection

@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    {{-- Search and filter bar --}}
    <div class="p-4 border-b border-[var(--color-line)] bg-[var(--color-paper-dim)]/30">
        <form method="GET" action="{{ route('admin.attributes.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <label for="search" class="sr-only">Search attributes</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           name="search"
                           id="search"
                           value="{{ request('search', '') }}"
                           placeholder="Search by name, slug, or type…"
                           class="w-full pl-10 pr-4 py-2 border hairline bg-[var(--color-paper)] rounded-lg text-sm focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <select name="type" class="border hairline bg-[var(--color-paper)] px-3 py-2 rounded-lg text-sm focus:outline-none focus:border-[var(--color-ink)]" aria-label="Filter by type">
                    <option value="">All Types</option>
                    @foreach(\App\Enums\AttributeType::options() as $type)
                        <option value="{{ $type['value'] }}" {{ request('type') === $type['value'] ? 'selected' : '' }}>{{ $type['label'] }}</option>
                    @endforeach
                </select>
                <select name="filterable" class="border hairline bg-[var(--color-paper)] px-3 py-2 rounded-lg text-sm focus:outline-none focus:border-[var(--color-ink)]" aria-label="Filter by filterable">
                    <option value="">All</option>
                    <option value="1" {{ request('filterable') === '1' ? 'selected' : '' }}>Filterable</option>
                    <option value="0" {{ request('filterable') === '0' ? 'selected' : '' }}>Not Filterable</option>
                </select>
            </div>
        </form>
    </div>

    {{-- Attributes table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Type</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Values</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Properties</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Used By</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($attributes ?? [] as $attribute)
                    <tr class="hover:bg-[var(--color-paper-dim)]/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[var(--color-red)]/10 text-[var(--color-red)] font-medium text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="font-medium text-[var(--color-ink)]">{{ $attribute->name }}</p>
                                    <p class="text-xs text-[var(--color-steel)] font-mono">{{ $attribute->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @php
                                $typeLabels = [
                                    'text' => 'Text',
                                    'number' => 'Number',
                                    'select' => 'Select',
                                    'multiselect' => 'Multi Select',
                                    'color' => 'Color',
                                ];
                                $typeColors = [
                                    'text' => 'bg-blue-100 text-blue-800',
                                    'number' => 'bg-indigo-100 text-indigo-800',
                                    'select' => 'bg-green-100 text-green-800',
                                    'multiselect' => 'bg-yellow-100 text-yellow-800',
                                    'color' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $typeColors[$attribute->type] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $typeLabels[$attribute->type] ?? ucfirst($attribute->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            @php $valueCount = $attribute->values_count ?? $attribute->values->count(); @endphp
                            @if($valueCount > 0)
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    {{ number_format($valueCount) }} value{{ $valueCount !== 1 ? 's' : '' }}
                                </span>
                                @if($valueCount <= 10)
                                    <span class="text-xs text-[var(--color-ink-soft)] ml-2">{{ $attribute->values->pluck('value')->implode(', ') }}</span>
                                @endif
                            @else
                                <span class="text-[var(--color-steel)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $attribute->is_filterable ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                    {{ $attribute->is_filterable ? 'Filterable' : 'Not Filterable' }}
                                </span>
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $attribute->is_variant ? 'bg-purple-100 text-purple-800' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                    {{ $attribute->is_variant ? 'Variants' : 'Standard' }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            @php $inUse = (int) ($productsInUse[$attribute->getKey()] ?? 0); @endphp
                            @if($inUse > 0)
                                <span class="inline-flex items-center gap-1 text-[var(--color-ink)]">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    {{ number_format($inUse) }} product{{ $inUse !== 1 ? 's' : '' }}
                                </span>
                                <span class="block text-xs text-[var(--color-ink-soft)] mt-1">will be unlinked on delete</span>
                            @else
                                <span class="text-[var(--color-steel)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.attributes.edit', $attribute->getKey()) }}"
                                   class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium transition">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('admin.attributes.destroy', $attribute->getKey()) }}" data-confirm="{{ $inUse > 0 ? 'Delete this attribute? Its '.$inUse.' '.\Illuminate\Support\Str::plural('product', $inUse).' will be unlinked.' : 'Delete this attribute?' }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium transition">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center">
                            <div class="inline-flex flex-col items-center gap-3">
                                <div class="w-16 h-16 rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center">
                                    <svg class="w-8 h-8 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-[var(--color-ink)] font-medium">No attributes yet</p>
                                    <p class="text-[var(--color-steel)] text-sm mt-1">Create your first attribute to start defining product specifications</p>
                                </div>
                                <a href="{{ route('admin.attributes.create') }}" class="bg-[var(--color-red)] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition mt-2">
                                    Create Attribute
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(isset($attributes) && $attributes->hasPages())
    <div class="px-4 py-3 border-t border-[var(--color-line)]">
        {{ $attributes->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection