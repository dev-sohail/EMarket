@extends('layouts.admin')

@section('title', 'Real-time Analytics')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Real-time Analytics</h1>
        <p class="text-[var(--color-steel)] mt-1">Visitors active in the last 5 minutes</p>
    </div>
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center gap-2 text-sm text-[var(--color-steel)]">
            <span class="w-2 h-2 rounded-full bg-[#1B7A43] animate-pulse"></span>
            Live
        </span>
        {{-- The old <meta http-equiv="refresh"> sat inside @section('header'),
             which layouts.admin yields inside <main>. Browsers only honour a
             refresh meta in <head>, so the page never actually refreshed. --}}
        <button type="button" data-action="reloadAnalyticsPage" class="px-4 py-2 border hairline rounded-lg text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)] transition-colors inline-flex items-center">
            <i class="fa-solid fa-rotate mr-2"></i>Refresh
        </button>
    </div>
</div>
@endsection

@section('content')
@include('admin.analytics._nav')

@php
    $activePageViews = $recentActivity ? $recentActivity->count() : 0;
    $realtimeStats = [
        ['label' => 'Active Users', 'value' => number_format($activeUsers), 'icon' => 'fa-user-clock', 'note' => 'Seen in the last 5 minutes'],
        ['label' => 'Page Views (5 min)', 'value' => number_format($activePageViews), 'icon' => 'fa-eye', 'note' => 'Latest recorded views'],
        ['label' => 'Top Active Pages', 'value' => number_format($topActivePages ? $topActivePages->count() : 0), 'icon' => 'fa-file-lines', 'note' => 'Pages with live traffic'],
        ['label' => 'Traffic Sources', 'value' => number_format($trafficSources ? $trafficSources->count() : 0), 'icon' => 'fa-diagram-project', 'note' => 'Distinct referrers right now'],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach($realtimeStats as $stat)
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-[var(--color-steel)]">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ $stat['value'] }}</p>
                    <p class="text-xs text-[var(--color-steel)] mt-2">{{ $stat['note'] }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid {{ $stat['icon'] }} text-[var(--color-ink-soft)]"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Top Active Pages</h2>
        <div class="space-y-3">
            @forelse($topActivePages as $page)
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ $page['path'] }}" class="text-sm text-[var(--color-ink)] hover:text-[var(--color-red)] truncate font-mono text-xs">{{ $page['path'] }}</a>
                    <span class="text-sm font-medium text-[var(--color-ink-soft)] bg-[var(--color-paper-dim)] px-2 py-1 rounded whitespace-nowrap">{{ number_format($page['views']) }} views</span>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)] py-6 text-center">No pages are being viewed right now.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Traffic Sources</h2>
        <div class="space-y-3">
            @forelse($trafficSources as $source)
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-2 text-sm text-[var(--color-ink)] min-w-0">
                        <i class="fa-solid {{ blank($source['referrer'] ?? null) ? 'fa-link-slash' : 'fa-arrow-right-from-bracket' }} text-[var(--color-steel)] text-xs w-4"></i>
                        <span class="truncate">{{ $source['referrer'] ?? 'Direct' }}</span>
                    </span>
                    <span class="text-sm font-medium text-[var(--color-ink-soft)] bg-[var(--color-paper-dim)] px-2 py-1 rounded whitespace-nowrap">{{ number_format($source['visitors']) }} visitors</span>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)] py-6 text-center">No traffic sources in this window.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden mt-6">
    <div class="p-4 border-b hairline flex items-center justify-between">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Recent Activity</h2>
        <span class="text-xs text-[var(--color-steel)]">Newest first</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Path</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Country</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Device</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($recentActivity as $activity)
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-4 py-3 text-[var(--color-ink)] font-mono text-xs">{{ $activity['path'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 text-[var(--color-steel)]">
                                <i class="fa-solid fa-globe text-[10px]"></i>
                                {{ $activity['country'] ?? 'Unknown' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 capitalize text-[var(--color-steel)]">{{ $activity['device_type'] ?? 'Unknown' }}</td>
                        <td class="px-4 py-3 text-right text-[var(--color-steel)] font-mono text-xs">{{ $activity['viewed_at']?->format('H:i:s') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-bolt text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No activity in the last 5 minutes</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">This screen fills in as visitors browse the storefront.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection