@extends('layouts.admin')

@section('title', 'Discount Campaigns')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Discount Campaigns</h1>
        <p class="text-[var(--color-steel)] mt-1">Create and manage percentage or fixed-amount discount campaigns</p>
    </div>
    <a href="{{ route('admin.campaigns.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Add Campaign
    </a>
</div>
@endsection

@section('content')
@php
    $summaryCards = [
        ['label' => 'Total Campaigns', 'value' => number_format($stats['total'] ?? 0), 'icon' => 'fa-cube', 'tone' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
        ['label' => 'Active', 'value' => number_format($stats['active'] ?? 0), 'icon' => 'fa-check-circle', 'tone' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
        ['label' => 'Inactive', 'value' => number_format($stats['inactive'] ?? 0), 'icon' => 'fa-pause-circle', 'tone' => 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]'],
        ['label' => 'Scheduled', 'value' => number_format($stats['scheduled'] ?? 0), 'icon' => 'fa-clock', 'tone' => 'bg-[#F59E0B]/10 text-[#B45309]'],
    ];

    $typeLabels = [
        'percentage' => 'Percentage',
        'fixed' => 'Fixed Amount',
    ];

    $typeColors = [
        'percentage' => 'bg-[#2563EB]/10 text-[#2563EB]',
        'fixed' => 'bg-[#7C3AED]/10 text-[#7C3AED]',
    ];
@endphp

{{-- Summary cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @foreach($summaryCards as $card)
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)] mt-1 truncate">{{ $card['value'] }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $card['tone'] }}">
                    <i class="fa-solid {{ $card['icon'] }}"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

{{-- Search and filter bar --}}
<form method="GET" action="{{ route('admin.campaigns.index') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-4">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[240px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by campaign name…"
                   class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
        </div>

        <select name="status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]" aria-label="Status">
            <option value="">All Statuses</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>

        <select name="type" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]" aria-label="Type">
            <option value="">All Types</option>
            <option value="percentage" {{ request('type') === 'percentage' ? 'selected' : '' }}>Percentage</option>
            <option value="fixed" {{ request('type') === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
        </select>

            <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">
            <i class="fa-solid fa-filter"></i>
            <span class="hidden sm:inline ml-1">Filter</span>
        </button>

        @if(request('search') || request('status') || request('type'))
            <a href="{{ route('admin.campaigns.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
        @endif
    </div>
</form>

{{-- Campaigns table --}}
<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Campaign</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Type</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Value</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Scope</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Schedule</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($campaigns ?? [] as $campaign)
                    @php
                        $scheduleState = 'no-dates';
                        if ($campaign->is_active && $campaign->starts_at?->lte(now()) && $campaign->ends_at?->gte(now())) {
                            $scheduleState = 'live';
                        } elseif ($campaign->starts_at?->gt(now())) {
                            $scheduleState = 'scheduled';
                        } elseif ($campaign->ends_at?->lt(now())) {
                            $scheduleState = 'ended';
                        }
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)]/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--color-red)]/10 text-[var(--color-red)]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                </span>
                                <div>
                                    <p class="font-medium text-[var(--color-ink)]">{{ $campaign->name }}</p>
                                    <p class="text-xs text-[var(--color-steel)] font-mono mt-0.5">{{ $campaign->getKey() }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $typeColors[$campaign->type] ?? 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                {{ $typeLabels[$campaign->type] ?? ucfirst($campaign->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-[var(--color-ink)] price">
                                @if($campaign->type === 'percentage')
                                    {{ $campaign->value }}%
                                @else
                                    {{ format_currency($campaign->value) }}
                                @endif
                            </span>
                            @if($campaign->minimum_order > 0)
                                <span class="block text-xs text-[var(--color-steel)] mt-0.5">Min order {{ format_currency($campaign->minimum_order) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            <div class="flex items-center gap-3">
                                @if($campaign->products_count > 0)
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fa-solid fa-box"></i>
                                        {{ $campaign->products_count }}
                                    </span>
                                @endif
                                @if($campaign->categories_count > 0)
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fa-solid fa-layer-group"></i>
                                        {{ $campaign->categories_count }}
                                    </span>
                                @endif
                                @if($campaign->products_count === 0 && $campaign->categories_count === 0)
                                    <span class="text-xs">All products</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($campaign->starts_at || $campaign->ends_at)
                                <span class="block text-sm text-[var(--color-steel)]">
                                    {{ $campaign->starts_at?->format('M d, Y') ?? 'Anytime' }} → {{ $campaign->ends_at?->format('M d, Y') ?? 'Ongoing' }}
                                </span>
                                @if($scheduleState === 'live')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-1 text-xs font-medium rounded-full bg-[#1B7A43]/10 text-[#1B7A43]">
                                        <span class="w-1.5 h-1.5 bg-[#1B7A43] rounded-full"></span>
                                        Live
                                    </span>
                                @elseif($scheduleState === 'scheduled')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-1 text-xs font-medium rounded-full bg-[#F59E0B]/10 text-[#B45309]">
                                        <span class="w-1.5 h-1.5 bg-[#F59E0B] rounded-full animate-pulse"></span>
                                        Scheduled
                                    </span>
                                @elseif($scheduleState === 'ended')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-1 text-xs font-medium rounded-full bg-[var(--color-steel)]/15 text-[var(--color-steel)]">
                                        <span class="w-1.5 h-1.5 bg-[var(--color-steel)] rounded-full"></span>
                                        Ended
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-1 text-xs font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-steel)]">
                                        No schedule
                                    </span>
                                @endif
                            @else
                                <span class="text-sm text-[var(--color-steel)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $campaign->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                                {{ $campaign->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.campaigns.toggleStatus', $campaign->getKey()) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs text-[var(--color-ink-soft)] hover:text-[var(--color-red)] px-2.5 py-1 border border-[var(--color-line)] rounded hover:bg-[var(--color-paper-dim)] transition">
                                        {{ $campaign->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.campaigns.edit', $campaign->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium transition">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign->getKey()) }}" data-confirm="Delete &ldquo;{{ $campaign->name }}&rdquo;? This cannot be undone.">
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
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="inline-flex flex-col items-center gap-3">
                                <div class="w-14 h-14 rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center">
                                    <svg class="w-7 h-7 text-[var(--color-steel)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.983 0a9.97 9.97 0 013.467 1.657A9.995 9.995 0 0116.7 20.343 9.97 9.97 0 0112.217 22.92v-2.642a7.5 7.5 0 10-2.7-12.4z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-[var(--color-ink)] font-medium">No campaigns found</p>
                                    <p class="text-[var(--color-steel)] text-sm mt-1">Create your first discount campaign to start offering promotions</p>
                                </div>
                                <a href="{{ route('admin.campaigns.create') }}" class="bg-[var(--color-red)] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition mt-2">
                                    Create Campaign
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if(isset($campaigns) && $campaigns->hasPages())
        <div class="px-4 py-3 border-t border-[var(--color-line)]">
            {{ $campaigns->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
