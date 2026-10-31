@extends('layouts.admin')

@section('title', 'Categories')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Categories</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">The catalogue tree that drives navigation and product grouping.</p>
    </div>
    <a href="{{ route('admin.categories.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
        <i class="fa-solid fa-plus"></i>
        Add Category
    </a>
</div>
@endsection

@section('content')
@php
    $categoryStats = [
        ['label' => 'Total Categories', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-sitemap', 'tone' => 'neutral', 'query' => []],
        ['label' => 'Active', 'value' => $stats['active'] ?? 0, 'icon' => 'fa-circle-check', 'tone' => 'success', 'query' => ['status' => 'active']],
        ['label' => 'Inactive', 'value' => $stats['inactive'] ?? 0, 'icon' => 'fa-circle-pause', 'tone' => 'danger', 'query' => ['status' => 'inactive']],
        ['label' => 'Top Level', 'value' => $stats['top_level'] ?? 0, 'icon' => 'fa-folder-open', 'tone' => 'warning', 'query' => []],
    ];
    $activeFilterCount = count(array_filter($filters ?? [], fn ($value) => $value !== null && $value !== ''));

    // Categories form a tree but the payload is one flat collection, so depth
    // is resolved here. A child used to sit flush against its parent with only
    // the parent's name to tell them apart.
    $byParent = [];
    foreach ($categories as $category) {
        $byParent[$category->parent_id ?? '__root__'][] = $category;
    }

    // Roll each category's descendant product count up the tree: a top-level
    // category holding only subcategories would otherwise report zero products
    // and look empty. Computed from the unfiltered set, otherwise filtering by
    // parent would hide the very descendants being counted.
    $directProducts = ($all ?? $categories)->keyBy(fn ($category) => $category->getKey());
    $allByParent = [];
    foreach ($all ?? $categories as $category) {
        $allByParent[$category->parent_id ?? '__root__'][] = $category;
    }

    $subtreeProducts = function (string $parentId) use (&$subtreeProducts, $directProducts, $allByParent): int {
        $total = 0;

        foreach ($allByParent[$parentId] ?? [] as $child) {
            $total += $directProducts[$child->getKey()]->products_count ?? 0;
            $total += $subtreeProducts($child->getKey());
        }

        return $total;
    };
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @foreach($categoryStats as $stat)
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
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or slug…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            </div>

            <select name="parent" data-auto-submit aria-label="Parent category" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Levels</option>
                @foreach($parents ?? [] as $parent)
                    <option value="{{ $parent->getKey() }}" @selected(($filters['parent'] ?? null) === $parent->getKey())>{{ $parent->name }}</option>
                @endforeach
            </select>

            <select name="status" data-auto-submit aria-label="Status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Statuses</option>
                <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive</option>
            </select>

            <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Filter</button>

            @if($activeFilterCount > 0)
                <a href="{{ route('admin.categories.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
            @endif
        </div>

        @if($activeFilterCount > 0)
            <p class="text-sm text-[var(--color-steel)] pt-3 border-t hairline">
                {{ number_format($categories->count()) }} categor{{ $categories->count() === 1 ? 'y' : 'ies' }} match
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
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Parent</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Products</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Subcategories</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @php
                    // Rendered depth-first from the roots so children always
                    // follow their parent, whichever subset the filters left.
                    $rows = [];
                    $walk = function ($parentId, $depth) use (&$walk, &$rows, $byParent, $subtreeProducts) {
                        foreach ($byParent[$parentId] ?? [] as $category) {
                            $rows[] = ['category' => $category, 'depth' => $depth];
                            $walk($category->getKey(), $depth + 1);
                        }
                    };
                    $walk('__root__', 0);

                    // A row whose parent was filtered out has no root to hang
                    // from, so the walk never reaches it. Without this pass,
                    // filtering by parent returned an empty table even though
                    // that parent had matching children.
                    $rendered = array_map(fn ($row) => $row['category']->getKey(), $rows);

                    foreach ($categories as $category) {
                        if (! in_array($category->getKey(), $rendered, true)) {
                            $rows[] = ['category' => $category, 'depth' => 1];
                        }
                    }
                @endphp

                @forelse($rows as $row)
                    @php
                        $category = $row['category'];
                        $depth = $row['depth'];
                        $directCount = $category->products_count ?? 0;
                        $nestedCount = $subtreeProducts($category->getKey());
                        $childCount = $category->children_count ?? 0;
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors {{ $category->is_active ? '' : 'opacity-70' }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3" style="padding-left: {{ $depth * 1.25 }}rem">
                                @if($depth > 0)
                                    {{-- Connector so the nesting reads as a tree
                                         rather than as an indented list. --}}
                                    <i class="fa-solid fa-chevron-right text-[var(--color-line)] text-xs"></i>
                                @endif
                                <div class="w-10 h-10 bg-[var(--color-paper-dim)] rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden">
                                    @if($category->image)
                                        <img src="{{ asset('storage/' . $category->image) }}" alt="" class="w-full h-full object-cover">
                                    @else
                                        <i class="fa-solid fa-folder text-[var(--color-steel)]"></i>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-[var(--color-ink)] text-sm truncate">{{ $category->name }}</p>
                                    <p class="text-xs text-[var(--color-steel)] font-mono truncate">{{ $category->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $category->parent?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                            <span class="font-medium text-[var(--color-ink)]">{{ number_format($directCount) }}</span>
                            @if($nestedCount > 0)
                                <span class="block text-xs text-[var(--color-steel)]">
                                    {{ number_format($nestedCount) }} product{{ $nestedCount === 1 ? '' : 's' }} in subcategories
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            @if($childCount > 0)
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]">
                                    <i class="fa-solid fa-sitemap text-[0.6rem]"></i>
                                    {{ $childCount }} child{{ $childCount === 1 ? '' : 'ren' }}
                                </span>
                            @else
                                <span class="text-[var(--color-steel)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            {{-- The toggle writes through to the storefront cache,
                                 so an inactive category really does disappear
                                 from the shop rather than only from this list. --}}
                            <form method="POST" action="{{ route('admin.categories.toggleStatus', $category->getKey()) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $category->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}" title="{{ $category->is_active ? 'Click to deactivate' : 'Click to activate' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $category->is_active ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}"></span>
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.categories.edit', $category->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category->getKey()) }}" data-confirm="Delete &quot;{{ $category->name }}&quot;?{{ $directCount > 0 ? ' It still holds ' . $directCount . ' product(s), so this will be refused.' : '' }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm {{ $directCount > 0 ? 'opacity-60' : '' }}">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-folder-plus text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">
                                {{ $activeFilterCount > 0 ? 'No categories match these filters' : 'No categories yet' }}
                            </p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if($activeFilterCount > 0)
                                    <a href="{{ route('admin.categories.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see the full tree.
                                @else
                                    <a href="{{ route('admin.categories.create') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Create your first category</a> to start building the catalogue.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection