@extends('layouts.admin')

@section('title', 'Marketing Analytics')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Marketing Analytics</h1>
        <p class="text-[var(--color-steel)] mt-1">Traffic sources and conversion performance</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <select name="range" data-auto-submit aria-label="Reporting period" class="border hairline rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="7" @selected($range == 7)>Last 7 days</option>
            <option value="30" @selected($range == 30)>Last 30 days</option>
            <option value="90" @selected($range == 90)>Last 90 days</option>
            <option value="365" @selected($range == 365)>Last 365 days</option>
        </select>
    </form>
</div>
@endsection

@section('content')
@include('admin.analytics._nav')

@php
    // Visitors-per-conversion is the number that actually judges a channel;
    // the old page showed only the headline conversion rate.
    $visitorsPerConversion = $totalConversions > 0
        ? round($totalVisitors / $totalConversions, 1)
        : 0;

    $marketingStats = [
        ['label' => 'Total Visitors', 'value' => number_format($totalVisitors), 'icon' => 'fa-users', 'note' => 'Unique visitors in period'],
        ['label' => 'Total Sessions', 'value' => number_format($totalSessions), 'icon' => 'fa-clock', 'note' => 'Every visit recorded'],
        ['label' => 'Conversions', 'value' => number_format($totalConversions), 'icon' => 'fa-cart-shopping', 'note' => 'Orders attributed to traffic'],
        ['label' => 'Conversion Rate', 'value' => $conversionRate.'%', 'icon' => 'fa-bullseye', 'note' => number_format($visitorsPerConversion, 1).' visitors per conversion'],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach($marketingStats as $stat)
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-[var(--color-steel)]">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)] mt-1 truncate">{{ $stat['value'] }}</p>
                    <p class="text-xs text-[var(--color-steel)] mt-2">{{ $stat['note'] }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg bg-[var(--color-paper-dim)] flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid {{ $stat['icon'] }} text-[var(--color-ink-soft)]"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="p-4 border-b hairline flex items-center justify-between">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Top Referral Sources</h2>
        <span class="text-xs text-[var(--color-steel)]">{{ count($topReferrers) }} sources</span>
    </div>
    @php $referrerMax = (int) ($topReferrers->max('visitors') ?? 0); @endphp
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Referrer</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Visitors</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($topReferrers as $referrer)
                    @php
                        $share = $referrerMax > 0 ? ($referrer['visitors'] / $referrerMax) * 100 : 0;
                        $isDirect = blank($referrer['referrer']);
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center gap-2 text-[var(--color-ink)] min-w-0">
                                <i class="fa-solid {{ $isDirect ? 'fa-link-slash' : 'fa-arrow-right-from-bracket' }} text-[var(--color-steel)] text-xs w-4"></i>
                                <span class="truncate">{{ $isDirect ? 'Direct' : $referrer['referrer'] }}</span>
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right text-[var(--color-ink-soft)]">{{ number_format($referrer['visitors']) }}</td>
                        <td class="px-6 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-24 bg-[var(--color-paper-dim)] rounded-full h-1.5">
                                    <div class="bg-[var(--color-red)] h-1.5 rounded-full" style="width: {{ number_format($share, 1) }}%"></div>
                                </div>
                                <span class="text-xs text-[var(--color-steel)] w-10 text-right">{{ number_format($share, 0) }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-bullhorn text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No referral data yet</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">Sources appear here once visitors arrive from outside the store.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection