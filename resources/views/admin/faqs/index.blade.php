@extends('layouts.admin')

@section('title', 'FAQs')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">FAQs</h1>
        <p class="text-[var(--color-steel)] mt-1">Questions shown on the storefront FAQ page, ordered by sort order</p>
    </div>
    <a href="{{ route('admin.faqs.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Add FAQ
    </a>
</div>
@endsection

@section('content')
<div class="space-y-6">
    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('admin.faqs.index') }}"
           class="bg-[var(--color-paper)] rounded-lg border hairline p-4 hover:border-[var(--color-ink)] transition {{ request('status') === null ? 'ring-1 ring-[var(--color-ink)]' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Total</span>
                <span class="w-8 h-8 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center text-[var(--color-steel)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.431 2.438-3.004 2.75-.635.127-1.209.298-1.524.741-.187.263-.25.415-.25.741 0 .414.336.75.75.75h.75c0 .414.336.75.75.75.414 0 .75-.336.75-.75v-.11c.06-.24.166-.453.314-.614.215-.233.516-.455.955-.63-.098-.648-.553-1.235-1.28-1.602A4.44 4.44 0 0012 8c-2.348 0-4.265 1.765-4.59 4.083M12 20h.01"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-2">{{ number_format($stats['total'] ?? 0) }}</p>
            <p class="text-xs text-[var(--color-steel)] mt-0.5">All questions</p>
        </a>

        <a href="{{ route('admin.faqs.index', ['status' => 'active']) }}"
           class="bg-[var(--color-paper)] rounded-lg border hairline p-4 hover:border-[var(--color-ink)] transition {{ request('status') === 'active' ? 'ring-1 ring-[var(--color-ink)]' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Published</span>
                <span class="w-8 h-8 rounded-lg bg-[#1B7A43]/10 flex items-center justify-center text-[#1B7A43]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-2">{{ number_format($stats['active'] ?? 0) }}</p>
            <p class="text-xs text-[var(--color-steel)] mt-0.5">Visible on storefront</p>
        </a>

        <a href="{{ route('admin.faqs.index', ['status' => 'inactive']) }}"
           class="bg-[var(--color-paper)] rounded-lg border hairline p-4 hover:border-[var(--color-ink)] transition {{ request('status') === 'inactive' ? 'ring-1 ring-[var(--color-ink)]' : '' }}">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Hidden</span>
                <span class="w-8 h-8 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center text-[var(--color-steel)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.6 10.6a3 3 0 004.2 4.2M9.9 5.2A9.7 9.7 0 0112 5c5 0 9 4.5 9 7a11.6 11.6 0 01-2.7 3.9M6.6 6.6C4.5 8 3 10.3 3 12c0 2.5 4 7 9 7 1.6 0 3.1-.4 4.4-1.1"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-2">{{ number_format($stats['inactive'] ?? 0) }}</p>
            <p class="text-xs text-[var(--color-steel)] mt-0.5">Not shown to customers</p>
        </a>
    </div>

    {{-- Listing --}}
    <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
        <div class="p-4 border-b border-[var(--color-line)] bg-[var(--color-paper-dim)]/30">
            <form method="GET" action="{{ route('admin.faqs.index') }}" class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[220px]">
                    <label for="search" class="sr-only">Search FAQs</label>
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           name="search"
                           id="search"
                           value="{{ request('search', '') }}"
                           placeholder="Search questions or answers…"
                           class="w-full pl-10 pr-4 py-2 border hairline bg-[var(--color-paper)] rounded-lg text-sm focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20">
                </div>

                <label for="status" class="sr-only">Filter by status</label>
                <select name="status" id="status" class="border hairline bg-[var(--color-paper)] px-3 py-2 rounded-lg text-sm focus:outline-none focus:border-[var(--color-ink)]">
                    <option value="">All statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Published only</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Hidden only</option>
                </select>

                <label for="sort" class="sr-only">Sort FAQs</label>
                <select name="sort" id="sort" class="border hairline bg-[var(--color-paper)] px-3 py-2 rounded-lg text-sm focus:outline-none focus:border-[var(--color-ink)]">
                    <option value="sort_asc" {{ request('sort', 'sort_asc') === 'sort_asc' ? 'selected' : '' }}>Sort order ↑</option>
                    <option value="sort_desc" {{ request('sort') === 'sort_desc' ? 'selected' : '' }}>Sort order ↓</option>
                    <option value="question_asc" {{ request('sort') === 'question_asc' ? 'selected' : '' }}>Question A–Z</option>
                    <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Newest first</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest first</option>
                </select>

                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium bg-[var(--color-ink)] text-white hover:opacity-90 transition">Apply</button>

                @if(request()->hasAny(['search', 'status', 'sort']))
                    <a href="{{ route('admin.faqs.index') }}" class="px-3 py-2 text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)] transition">Reset</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider w-16">#</th>
                        <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Question</th>
                        <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider w-40">Status</th>
                        <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider w-32">Updated</th>
                        <th scope="col" class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-line)]">
                    @forelse($faqs ?? [] as $faq)
                        <tr class="hover:bg-[var(--color-paper-dim)]/30 transition-colors {{ $faq->is_active ? '' : 'bg-[var(--color-paper-dim)]/40' }}">
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center justify-center min-w-[2rem] h-7 px-2 rounded-lg bg-[var(--color-paper-dim)] text-[var(--color-steel)] text-xs font-medium tabular-nums">
                                    {{ $faq->sort_order }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-[var(--color-ink)] text-sm {{ $faq->is_active ? '' : 'text-[var(--color-steel)]' }}">{{ $faq->question }}</p>
                                <p class="text-xs text-[var(--color-steel)] mt-1 line-clamp-2 max-w-2xl">{{ \Illuminate\Support\Str::limit(strip_tags($faq->answer), 140) }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $faq->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {{ $faq->is_active ? 'Published' : 'Hidden' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $faq->updated_at?->diffForHumans() }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('admin.faqs.toggleStatus', $faq->getKey()) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-sm font-medium transition {{ $faq->is_active ? 'text-[var(--color-steel)] hover:text-[var(--color-ink)]' : 'text-[#1B7A43] hover:text-[#1B7A43]' }}" title="{{ $faq->is_active ? 'Hide from storefront' : 'Publish to storefront' }}">
                                            {{ $faq->is_active ? 'Hide' : 'Publish' }}
                                        </button>
                                    </form>
                                    <span class="w-px h-4 bg-[var(--color-line)]"></span>
                                    <a href="{{ route('admin.faqs.edit', $faq->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium transition">Edit</a>
                                    <form method="POST" action="{{ route('admin.faqs.destroy', $faq->getKey()) }}" data-confirm="Delete this FAQ? This cannot be undone." class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium transition">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <div class="inline-flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center">
                                        <svg class="w-8 h-8 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.431 2.438-3.004 2.75-.635.127-1.209.298-1.524.741-.187.263-.25.415-.25.741 0 .414.336.75.75.75h.75c0 .414.336.75.75.75.414 0 .75-.336.75-.75v-.11c.06-.24.166-.453.314-.614.215-.233.516-.455.955-.63-.098-.648-.553-1.235-1.28-1.602A4.44 4.44 0 0012 8c-2.348 0-4.265 1.765-4.59 4.083M12 20h.01"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-[var(--color-ink)] font-medium">
                                            {{ request()->hasAny(['search', 'status', 'sort']) ? 'No FAQs match these filters.' : 'No FAQs yet.' }}
                                        </p>
                                        <p class="text-[var(--color-steel)] text-sm mt-1">
                                            {{ request()->hasAny(['search', 'status', 'sort']) ? 'Try a different search term or clear the filters.' : 'Add the questions your customers ask most often.' }}
                                        </p>
                                    </div>
                                    @if(request()->hasAny(['search', 'status', 'sort']))
                                        <a href="{{ route('admin.faqs.index') }}" class="border hairline px-5 py-2.5 rounded-lg font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition mt-2">Clear filters</a>
                                    @else
                                        <a href="{{ route('admin.faqs.create') }}" class="bg-[var(--color-red)] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition mt-2">Create FAQ</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($faqs) && $faqs->hasPages())
            <div class="px-4 py-3 border-t border-[var(--color-line)] flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-[var(--color-steel)]">
                    Showing {{ $faqs->firstItem() }}–{{ $faqs->lastItem() }} of {{ $faqs->total() }}
                </p>
                {{ $faqs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
