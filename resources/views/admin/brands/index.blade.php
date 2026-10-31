@extends('layouts.admin')

@section('title', 'Brands')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Brands</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Manufacturers and labels your products can be grouped under.</p>
    </div>
    <a href="{{ route('admin.brands.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
        <i class="fa-solid fa-plus"></i>
        Add Brand
    </a>
</div>
@endsection

@section('content')
@php
    $brandStats = [
        ['label' => 'Total Brands', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-tags', 'tone' => 'neutral', 'query' => []],
        ['label' => 'Active', 'value' => $stats['active'] ?? 0, 'icon' => 'fa-circle-check', 'tone' => 'success', 'query' => ['status' => 'active']],
        ['label' => 'Inactive', 'value' => $stats['inactive'] ?? 0, 'icon' => 'fa-circle-pause', 'tone' => 'danger', 'query' => ['status' => 'inactive']],
        ['label' => 'No Products', 'value' => $stats['unused'] ?? 0, 'icon' => 'fa-box-open', 'tone' => 'warning', 'query' => []],
    ];
    $activeFilterCount = count(array_filter($filters ?? [], fn ($value) => $value !== null && $value !== ''));
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @foreach($brandStats as $stat)
        @php
            $toneClasses = match ($stat['tone']) {
                'success' => ['value' => 'text-[#1B7A43]', 'icon' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
                'danger' => ['value' => 'text-[var(--color-red)]', 'icon' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]'],
                'warning' => ['value' => 'text-[#B45309]', 'icon' => 'bg-[#F59E0B]/10 text-[#B45309]'],
                default => ['value' => 'text-[var(--color-ink)]', 'icon' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
            };
        @endphp
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold {{ $toneClasses['value'] }} mt-1">{{ number_format($stat['value']) }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $toneClasses['icon'] }}">
                    <i class="fa-solid {{ $stat['icon'] }}"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-6">
    <form method="GET" class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name, slug or website…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            </div>

            <select name="status" data-auto-submit aria-label="Status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Statuses</option>
                <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive</option>
            </select>

            <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Filter</button>

            @if($activeFilterCount > 0)
                <a href="{{ route('admin.brands.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
            @endif
        </div>

        @if($activeFilterCount > 0)
            <p class="text-sm text-[var(--color-steel)] pt-3 border-t hairline">
                {{ number_format($brands->total()) }} brand{{ $brands->total() === 1 ? '' : 's' }} match
                <span class="text-[var(--color-red)]">&middot; {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }} applied</span>
            </p>
        @endif
    </form>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Brand</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Slug</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Website</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Products</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Order</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($brands as $brand)
                    @php $productCount = $brand->products_count ?? 0; @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors {{ $brand->is_active ? '' : 'opacity-70' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-[var(--color-paper-dim)] rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    @if($brand->logo)
                                        <img src="{{ asset('storage/' . $brand->logo) }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <i class="fa-solid fa-tag text-[var(--color-steel)]"></i>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-[var(--color-ink)] text-sm truncate">{{ $brand->name }}</p>
                                    @php $summary = trim(strip_tags((string) $brand->description)); @endphp
                                    @if($summary !== '')
                                        <p class="text-xs text-[var(--color-steel)] truncate max-w-xs">{{ $summary }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-xs text-[var(--color-steel)] font-mono">{{ $brand->slug }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if($brand->website)
                                <a href="{{ $brand->website }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-[var(--color-red)] hover:text-[var(--color-red-dark)]">
                                    <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                                    <span class="truncate max-w-[12rem]">{{ parse_url($brand->website, PHP_URL_HOST) ?: $brand->website }}</span>
                                </a>
                            @else
                                <span class="text-[var(--color-steel)]">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            @if($productCount > 0)
                                <span class="font-medium text-[var(--color-ink)]">{{ number_format($productCount) }}</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[#F59E0B]/10 text-[#B45309]">
                                    <i class="fa-solid fa-triangle-exclamation text-[0.6rem]"></i>
                                    Unused
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ number_format($brand->sort_order ?? 0) }}</td>
                        <td class="px-6 py-4">
                            {{-- The toggle writes through to the storefront cache,
                                 so an inactive brand really does disappear from
                                 the shop rather than only from this list. --}}
                            <form method="POST" action="{{ route('admin.brands.toggleStatus', $brand->getKey()) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $brand->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}" title="{{ $brand->is_active ? 'Click to deactivate' : 'Click to activate' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $brand->is_active ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}"></span>
                                    {{ $brand->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.brands.edit', $brand->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.brands.destroy', $brand->getKey()) }}" data-confirm="Delete &quot;{{ $brand->name }}&quot;?{{ $productCount > 0 ? ' It still has ' . $productCount . ' product(s), so this will be refused.' : '' }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm {{ $productCount > 0 ? 'opacity-60' : '' }}">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-tag text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">
                                {{ $activeFilterCount > 0 ? 'No brands match these filters' : 'No brands yet' }}
                            </p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if($activeFilterCount > 0)
                                    <a href="{{ route('admin.brands.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see every brand.
                                @else
                                    <a href="{{ route('admin.brands.create') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Create your first brand</a> to start grouping products.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($brands, 'links') && $brands->hasPages())
        <div class="p-4 border-t hairline">{{ $brands->links() }}</div>
    @endif
</div>
@endsection