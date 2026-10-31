@extends('layouts.admin')
@section('title', 'Enquiries')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Enquiries</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Manage customer enquiries and support requests</p>
    </div>
</div>
@endsection
@section('content')
<div class="bg-[var(--color-paper)] rounded-xl border shadow-sm">
    <!-- Stats Summary -->
    <div class="px-6 py-4 border-b bg-[var(--color-paper-dim)]/30 rounded-t-xl">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @foreach($stats as $key => $count)
                <a href="{{ route('admin.enquiries.index', array_merge(request()->query(), ['status' => $key === 'all' ? null : $key])) }}"
                   class="p-3 rounded-lg text-center transition-colors {{ request('status', 'all') === $key ? 'bg-[var(--color-red)] text-white shadow-[0_0_0_2px_var(--color-red)]' : 'bg-[var(--color-paper)] hover:bg-[var(--color-paper-dim)] border' }}">
                    <p class="text-2xl font-bold">{{ $count }}</p>
                    <p class="text-xs uppercase tracking-wide mt-1 opacity-80">{{ ucfirst($key) }}</p>
                </a>
            @endforeach
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="p-4 border-b flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <form method="GET" class="flex-1 max-w-md">
            <div class="relative">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search name, email, subject..."
                       class="w-full pl-10 pr-4 py-2 text-sm border rounded-lg bg-[var(--color-paper)] focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent transition-all">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
            </div>
        </form>

        <form method="GET" class="flex items-center gap-2">
            @foreach(request()->query() as $key => $value)
                @if($key !== 'status')
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <select name="status"
                    data-auto-submit
                    class="px-3 py-2 text-sm border rounded-lg bg-[var(--color-paper)] focus:ring-2 focus:ring-[var(--color-red)] focus:border-transparent transition-all appearance-none pr-8"
                    style="background-image: url('data:image/svg+xml;charset=US-ASCII,<svg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2012%2012%22%3E<path%20fill%3D%22%236b7280%22%20d%3D%22M6%208L1%203h10z%22%2F%3E</svg>'); background-repeat: no-repeat; background-position: right 0.625rem center;">
                <option value="">All Status</option>
                <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
                <option value="read" {{ request('status') === 'read' ? 'selected' : '' }}>Read</option>
                <option value="replied" {{ request('status') === 'replied' ? 'selected' : '' }}>Replied</option>
                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
            </select>
            <a href="{{ route('admin.enquiries.index') }}"
               class="px-3 py-2 text-sm border rounded-lg hover:bg-[var(--color-paper-dim)] transition-colors">
                Clear
            </a>
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-paper-dim)] border-b">
                <tr>
                    <th class="text-left px-6 py-4 font-medium text-[var(--color-ink-soft)] uppercase tracking-wider text-xs">Enquiry</th>
                    <th class="text-left px-6 py-4 font-medium text-[var(--color-ink-soft)] uppercase tracking-wider text-xs hidden md:table-cell">Contact</th>
                    <th class="text-left px-6 py-4 font-medium text-[var(--color-ink-soft)] uppercase tracking-wider text-xs hidden md:table-cell">Subject</th>
                    <th class="text-center px-6 py-4 font-medium text-[var(--color-ink-soft)] uppercase tracking-wider text-xs">Status</th>
                    <th class="text-left px-6 py-4 font-medium text-[var(--color-ink-soft)] uppercase tracking-wider text-xs hidden lg:table-cell">Received</th>
                    <th class="text-right px-6 py-4 font-medium text-[var(--color-ink-soft)] uppercase tracking-wider text-xs">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-border)]">
                @forelse($enquiries as $enquiry)
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors cursor-pointer {{ $enquiry->status === 'new' ? 'bg-[var(--color-red)]/5' : '' }}"
                        data-href="{{ route('admin.enquiries.show', $enquiry) }}">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-[var(--color-red)]/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-[var(--color-red)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-medium text-[var(--color-ink)]">{{ $enquiry->name }}</p>
                                    <p class="text-xs text-[var(--color-steel)]">{{ $enquiry->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell">
                            <p class="text-[var(--color-steel)]">{{ $enquiry->email }}</p>
                            @if($enquiry->user_id)
                                <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full bg-[var(--color-purple)]/10 text-[var(--color-purple)]">Registered User</span>
                            @else
                                <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full bg-[var(--color-amber)]/10 text-[var(--color-amber)]">Guest</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell max-w-xs">
                            <p class="font-medium text-[var(--color-ink)] truncate">{{ $enquiry->subject }}</p>
                            <p class="text-xs text-[var(--color-steel)] truncate mt-1">{{ Str::limit($enquiry->message, 100) }}</p>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $statusConfig = [
                                    'new' => ['bg' => 'bg-[var(--color-red)]/10', 'text' => 'text-[var(--color-red)]', 'dot' => 'bg-[var(--color-red)]', 'label' => 'New'],
                                    'read' => ['bg' => 'bg-[var(--color-blue)]/10', 'text' => 'text-[var(--color-blue)]', 'dot' => 'bg-[var(--color-blue)]', 'label' => 'Read'],
                                    'replied' => ['bg' => 'bg-[var(--color-purple)]/10', 'text' => 'text-[var(--color-purple)]', 'dot' => 'bg-[var(--color-purple)]', 'label' => 'Replied'],
                                    'closed' => ['bg' => 'bg-[var(--color-gray)]/10', 'text' => 'text-[var(--color-ink-soft)]', 'dot' => 'bg-[var(--color-gray)]', 'label' => 'Closed'],
                                ];
                                $config = $statusConfig[$enquiry->status] ?? $statusConfig['new'];
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium {{ $config['bg'] }} {{ $config['text'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $config['dot'] }}"></span>
                                {{ $config['label'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-[var(--color-steel)] hidden lg:table-cell whitespace-nowrap">
                            {{ $enquiry->created_at->diffForHumans() }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.enquiries.show', $enquiry) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-[var(--color-red)] hover:text-[var(--color-ink)] transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <svg class="w-16 h-16 text-[var(--color-steel)]/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <p class="text-[var(--color-ink)] font-medium">No enquiries found</p>
                                <p class="text-[var(--color-steel)] text-sm">Try adjusting your search or filters</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($enquiries->hasPages())
        <div class="px-6 py-4 border-t">
            {{ $enquiries->appends(request()->query())->links('pagination::tailwind') }}
        </div>
    @endif
</div>

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', () => {
    // Clickable rows
    document.querySelectorAll('[data-href]').forEach(row => {
        row.addEventListener('click', (e) => {
            if (!e.target.closest('a, button, select, input')) {
                window.location.href = row.dataset.href;
            }
        });
        row.style.cursor = 'pointer';
    });

    // Debounced search
    const searchInput = document.querySelector('input[name="search"]');
    let searchTimeout;
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchInput.closest('form').submit();
        }, 300);
    });
});
</script>
@endpush

@endsection
