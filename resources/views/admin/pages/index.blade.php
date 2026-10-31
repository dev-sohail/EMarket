@extends('layouts.admin')

@section('title', 'Pages')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Pages</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Static content pages such as About, Terms and contact blocks.</p>
    </div>
    <a href="{{ route('admin.pages.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2 whitespace-nowrap">
        <i class="fa-solid fa-plus"></i>
        Add Page
    </a>
</div>
@endsection

@section('content')
@php
    $pageStats = [
        ['label' => 'Total Pages', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-file-lines', 'tone' => 'neutral', 'query' => []],
        ['label' => 'Published', 'value' => $stats['published'] ?? 0, 'icon' => 'fa-circle-check', 'tone' => 'success', 'query' => ['status' => 'published']],
        ['label' => 'Drafts', 'value' => $stats['draft'] ?? 0, 'icon' => 'fa-pen-ruler', 'tone' => 'warning', 'query' => ['status' => 'draft']],
    ];

    $activeFilterCount = count(array_filter($filters ?? [], fn ($value) => $value !== null && $value !== ''));
@endphp

{{-- Stat tiles double as status filters: the numbers are already on screen,
     so no separate dropdown is needed to reach the same pages. --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    @foreach($pageStats as $stat)
        @php
            $toneClasses = match ($stat['tone']) {
                'success' => ['value' => 'text-[#1B7A43]', 'icon' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
                'warning' => ['value' => 'text-[#B45309]', 'icon' => 'bg-[#F59E0B]/10 text-[#B45309]'],
                default => ['value' => 'text-[var(--color-ink)]', 'icon' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
            };
            $isActive = ($filters['status'] ?? '') === ($stat['query']['status'] ?? null)
                && ($filters['status'] ?? '') !== '';
        @endphp
        <a href="{{ route('admin.pages.index', array_merge(request()->query(), $stat['query'])) }}"
           class="bg-[var(--color-paper)] rounded-lg border hairline p-5 transition {{ $isActive ? 'border-[var(--color-red)] ring-2 ring-[var(--color-red)]/20' : 'hover:border-[var(--color-ink-soft)]' }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold {{ $toneClasses['value'] }} mt-1">{{ number_format($stat['value']) }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $toneClasses['icon'] }}">
                    <i class="fa-solid {{ $stat['icon'] }}"></i>
                </div>
            </div>
        </a>
    @endforeach
</div>

{{-- Filter bar --}}
<div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-6">
    <form method="GET" action="{{ route('admin.pages.index') }}" class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px]">
                <label for="search" class="sr-only">Search pages</label>
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
                <input type="search" name="search" id="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by title or slug…" class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-red)]">
            </div>

            <select name="status" data-auto-submit aria-label="Status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="">All Statuses</option>
                <option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
            </select>

            <select name="sort" data-auto-submit aria-label="Sort order" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest First</option>
                <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest First</option>
                <option value="title_asc" @selected(($filters['sort'] ?? '') === 'title_asc')>Title A–Z</option>
                <option value="title_desc" @selected(($filters['sort'] ?? '') === 'title_desc')>Title Z–A</option>
            </select>

            <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Filter</button>

            @if($activeFilterCount > 0)
                <a href="{{ route('admin.pages.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
            @endif
        </div>

        @if($activeFilterCount > 0)
            <p class="text-sm text-[var(--color-steel)] pt-3 border-t hairline">
                {{ number_format($pages->total()) }} page{{ $pages->total() === 1 ? '' : 's' }} match
                <span class="text-[var(--color-red)]">&middot; {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }} applied</span>
            </p>
        @endif
    </form>
</div>

{{-- Pages table --}}
<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Page</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">URL</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Last Updated</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($pages ?? [] as $page)
                    <tr class="hover:bg-[var(--color-paper-dim)]/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] flex-shrink-0">
                                    <i class="fa-regular fa-file-lines"></i>
                                </span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="font-medium text-[var(--color-ink)] text-sm hover:text-[var(--color-red)] transition truncate block">
                                        {{ $page->title }}
                                    </a>
                                    <p class="text-xs text-[var(--color-steel)] font-mono">{{ $page->getKey() }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <a href="{{ route('page.show', $page->slug) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-mono text-xs text-[var(--color-steel)] hover:text-[var(--color-red)] transition">
                                /{{ $page->slug }}
                                <i class="fa-solid fa-arrow-up-right-from-square text-[0.6rem]"></i>
                            </a>
                        </td>
                        <td class="px-6 py-4">
                            {{-- The toggle writes straight through, so the badge is
                                 the control: publishing hides the page from the
                                 storefront immediately, not after a save. --}}
                            <form method="POST" action="{{ route('admin.pages.toggleStatus', $page) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full transition {{ $page->status === 'published' ? 'bg-[#1B7A43]/10 text-[#1B7A43] hover:bg-[#1B7A43]/20' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)] hover:bg-[var(--color-line)]' }}" title="{{ $page->status === 'published' ? 'Unpublish this page' : 'Publish this page' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $page->status === 'published' ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}"></span>
                                    {{ $page->status === 'published' ? 'Published' : 'Draft' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)] whitespace-nowrap">
                            {{ $page->updated_at?->format('M d, Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('page.show', $page->slug) }}" target="_blank" rel="noopener" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm" title="View on storefront">
                                    <i class="fa-solid fa-eye"></i>
                                    <span class="sr-only">View</span>
                                </a>
                                <a href="{{ route('admin.pages.edit', $page) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Edit</a>
                                <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="Delete &ldquo;{{ $page->title }}&rdquo;? This cannot be undone." class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-14 text-center">
                            <div class="w-14 h-14 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-regular fa-file-lines text-[var(--color-steel)] text-lg"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">
                                {{ $activeFilterCount > 0 ? 'No pages match these filters' : 'No pages yet' }}
                            </p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if($activeFilterCount > 0)
                                    <a href="{{ route('admin.pages.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see every page.
                                @else
                                    <a href="{{ route('admin.pages.create') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Create your first page</a> to add About, Terms or FAQ content.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(($pages ?? null) && $pages->hasPages())
        <div class="px-4 py-3 border-t hairline">
            {{ $pages->links() }}
        </div>
    @endif
</div>
@endsection
