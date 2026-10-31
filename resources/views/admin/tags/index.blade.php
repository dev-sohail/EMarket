@extends('layouts.admin')

@section('title', 'Tags')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Product Tags</h1>
        <p class="text-[var(--color-steel)] mt-1">Organize products with tags for filtering and discovery</p>
    </div>
    <a href="{{ route('admin.tags.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Add Tag
    </a>
</div>
@endsection

@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    {{-- Search and filter bar --}}
    <div class="p-4 border-b border-[var(--color-line)] bg-[var(--color-paper-dim)]/30">
        <form method="GET" action="{{ route('admin.tags.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <label for="search" class="sr-only">Search tags</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           name="search"
                           id="search"
                           value="{{ request('search', '') }}"
                           placeholder="Search by name or slug…"
                           class="w-full pl-10 pr-4 py-2 border hairline bg-[var(--color-paper)] rounded-lg text-sm focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <select name="sort" class="border hairline bg-[var(--color-paper)] px-3 py-2 rounded-lg text-sm focus:outline-none focus:border-[var(--color-ink)]" aria-label="Sort order">
                    <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name A–Z</option>
                    <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name Z–A</option>
                    <option value="products_desc" {{ request('sort') === 'products_desc' ? 'selected' : '' }}>Most Products</option>
                    <option value="products_asc" {{ request('sort') === 'products_asc' ? 'selected' : '' }}>Fewest Products</option>
                    <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Newest First</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                </select>
            </div>
        </form>
    </div>

    {{-- Tags table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Slug</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Products</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Created</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($tags ?? [] as $tag)
                    <tr class="hover:bg-[var(--color-paper-dim)]/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[var(--color-red)]/10 text-[var(--color-red)] font-medium text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                </span>
                                <div>
                                    <p class="font-medium text-[var(--color-ink)]">{{ $tag->name }}</p>
                                    <p class="text-xs text-[var(--color-steel)] font-mono">#{{ $tag->getKey() }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)] font-mono">{{ $tag->slug }}</td>
                        <td class="px-6 py-4">
                            @php $count = $tag->products_count ?? 0; @endphp
                            @if($count > 0)
                                <span class="inline-flex items-center gap-1 text-sm text-[var(--color-ink)]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    {{ number_format($count) }} product{{ $count !== 1 ? 's' : '' }}
                                    <span class="text-xs text-[var(--color-ink-soft)]">in use</span>
                                </span>
                            @else
                                <span class="text-sm text-[var(--color-steel)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $tag->created_at?->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.tags.edit', $tag->getKey()) }}"
                                   class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium transition">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('admin.tags.destroy', $tag->getKey()) }}" data-confirm="Delete &ldquo;{{ $tag->name }}&rdquo;? This cannot be undone." class="inline">
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
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="inline-flex flex-col items-center gap-3">
                                <div class="w-16 h-16 rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center">
                                    <svg class="w-8 h-8 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-[var(--color-ink)] font-medium">No tags found.</p>
                                    <p class="text-[var(--color-steel)] text-sm mt-1">Create your first tag to start organizing products</p>
                                </div>
                                <a href="{{ route('admin.tags.create') }}" class="bg-[var(--color-red)] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition mt-2">
                                    Create Tag
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(isset($tags) && $tags->hasPages())
    <div class="px-4 py-3 border-t border-[var(--color-line)]">
        {{ $tags->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection