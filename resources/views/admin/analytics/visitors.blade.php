@extends('layouts.admin')

@section('title', 'Visitor Traffic Analytics')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Visitor Traffic</h1>
        <p class="text-[var(--color-steel)] mt-1">Visitor behaviour and traffic sources</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <select name="range" data-auto-submit aria-label="Reporting period" class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="7" @selected($range == 7)>Last 7 days</option>
            <option value="30" @selected($range == 30)>Last 30 days</option>
            <option value="90" @selected($range == 90)>Last 90 days</option>
            <option value="365" @selected($range == 365)>Last 365 days</option>
        </select>
        <a href="{{ route('admin.analytics.export', ['report_type' => 'visitors']) }}"
           class="px-4 py-2 bg-[var(--color-red)] text-white rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition-colors inline-flex items-center">
            <i class="fa-solid fa-download mr-2"></i>Export
        </a>
    </form>
</div>
@endsection

@section('content')
@include('admin.analytics._nav')

@php
    // Derived ratios the old page implied but never stated: how many page
    // views a single visitor makes, and how long a session lasts on average.
    $viewsPerVisitor = $totalUniqueVisitors > 0 ? round($totalPageViews / $totalUniqueVisitors, 2) : 0;
    $pagesPerSession = $totalSessions > 0 ? round($totalPageViews / $totalSessions, 2) : 0;

    $visitorStats = [
        ['label' => 'Total Page Views', 'value' => number_format($totalPageViews), 'icon' => 'fa-eye', 'note' => 'Every page load recorded'],
        ['label' => 'Unique Visitors', 'value' => number_format($totalUniqueVisitors), 'icon' => 'fa-users', 'note' => number_format($viewsPerVisitor, 2).' views each'],
        ['label' => 'Total Sessions', 'value' => number_format($totalSessions), 'icon' => 'fa-clock', 'note' => number_format($pagesPerSession, 2).' pages each'],
        ['label' => 'Bounce Rate', 'value' => $bounceRate.'%', 'icon' => 'fa-arrow-right-arrow-left', 'note' => 'Single-page sessions'],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach($visitorStats as $stat)
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
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

<div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Traffic Overview</h2>
        <p class="text-xs text-[var(--color-steel)]">Sessions include repeat visits from the same visitor</p>
    </div>
    <div style="height: 340px; width: 100%;">
        <canvas id="visitorsChart"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    @php
        // The device list was rendered as raw lowercase words; give each the
        // Font Awesome glyph it deserves and a share-of-traffic bar.
        $deviceIcons = ['desktop' => 'fa-desktop', 'mobile' => 'fa-mobile-screen', 'tablet' => 'fa-tablet-screen-button'];
        $deviceMax = (int) ($deviceBreakdown->max('visitors') ?? 0);
    @endphp
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Device Breakdown</h2>
        <div class="space-y-4">
            @forelse($deviceBreakdown as $device)
                @php
                    $slug = strtolower((string) $device['device']);
                    $share = $deviceMax > 0 ? ($device['visitors'] / $deviceMax) * 100 : 0;
                @endphp
                <div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-2 text-sm text-[var(--color-ink)] capitalize min-w-0">
                            <i class="fa-solid {{ $deviceIcons[$slug] ?? 'fa-globe' }} text-[var(--color-steel)] text-xs w-4"></i>
                            <span class="truncate">{{ $device['device'] }}</span>
                        </span>
                        <span class="text-sm font-medium text-[var(--color-ink)] whitespace-nowrap">{{ number_format($device['visitors']) }}</span>
                    </div>
                    <div class="w-full bg-[var(--color-paper-dim)] rounded-full h-1.5 mt-1.5">
                        <div class="bg-[var(--color-ink-soft)] h-1.5 rounded-full" style="width: {{ number_format($share, 1) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)] py-4 text-center">No device data yet.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Top Pages</h2>
        <div class="space-y-3">
            @forelse($topPages as $page)
                <div class="flex items-center justify-between gap-3">
                    <a href="{{ $page['path'] }}" class="text-sm text-[var(--color-ink)] hover:text-[var(--color-red)] truncate font-mono text-xs">{{ $page['path'] }}</a>
                    <span class="text-sm font-medium text-[var(--color-ink-soft)] bg-[var(--color-paper-dim)] px-2 py-1 rounded whitespace-nowrap">{{ number_format($page['views']) }}</span>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)] py-4 text-center">No page views yet.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Top Referrers</h2>
        <div class="space-y-3">
            @forelse($topReferrers as $referrer)
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-[var(--color-ink)] truncate">{{ $referrer['referrer'] ?: 'Direct' }}</span>
                    <span class="text-sm font-medium text-[var(--color-ink-soft)] bg-[var(--color-paper-dim)] px-2 py-1 rounded whitespace-nowrap">{{ number_format($referrer['visitors']) }}</span>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)] py-4 text-center">No referrer data yet.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Browser Breakdown</h2>
        <div class="space-y-3">
            @forelse($browserBreakdown as $browser)
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-[var(--color-ink)] truncate">{{ $browser['browser'] ?: 'Unknown' }}</span>
                    <span class="text-sm font-medium text-[var(--color-ink-soft)] bg-[var(--color-paper-dim)] px-2 py-1 rounded whitespace-nowrap">{{ number_format($browser['visitors']) }}</span>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)] py-4 text-center">No browser data yet.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Country Breakdown</h2>
        <div class="space-y-3">
            @forelse($countryBreakdown as $country)
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-2 text-sm text-[var(--color-ink)] min-w-0">
                        <i class="fa-solid fa-globe text-[var(--color-steel)] text-xs w-4"></i>
                        <span class="truncate">{{ $country['country'] ?: 'Unknown' }}</span>
                    </span>
                    <span class="text-sm font-medium text-[var(--color-ink-soft)] bg-[var(--color-paper-dim)] px-2 py-1 rounded whitespace-nowrap">{{ number_format($country['visitors']) }}</span>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)] py-4 text-center">No country data yet.</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('visitorsChart');
    if (!canvas) return;

    const style = getComputedStyle(document.documentElement);
    const css = (name, fallback) => (style.getPropertyValue(name) || fallback).trim();
    const ink = css('--color-ink', '#14171a');
    const steel = css('--color-steel', '#6b6f76');
    const red = css('--color-red', '#d6001c');
    const line = css('--color-line', '#dedcd4');

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: @json($labels ?? []),
            datasets: [
                {
                    label: 'Page Views',
                    data: @json($pageViews ?? []),
                    borderColor: red,
                    backgroundColor: 'rgba(214, 0, 28, 0.08)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0.3,
                    fill: true,
                },
                {
                    label: 'Unique Visitors',
                    data: @json($uniqueVisitors ?? []),
                    borderColor: ink,
                    backgroundColor: 'rgba(20, 23, 26, 0.04)',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0.3,
                    fill: true,
                },
                {
                    label: 'Sessions',
                    data: @json($sessions ?? []),
                    borderColor: steel,
                    backgroundColor: 'rgba(107, 111, 118, 0.05)',
                    borderWidth: 2,
                    borderDash: [4, 4],
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: 0.3,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: line, drawBorder: false },
                    border: { display: false },
                    ticks: { color: steel, maxTicksLimit: 8, precision: 0 },
                },
                x: {
                    grid: { display: false },
                    border: { color: line },
                    ticks: { color: steel, maxTicksLimit: 12, maxRotation: 0, autoSkipPadding: 16 },
                },
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { color: steel, usePointStyle: true, pointStyle: 'line', boxWidth: 24 },
                },
            },
        },
    });
});
</script>
@endpush
@endsection