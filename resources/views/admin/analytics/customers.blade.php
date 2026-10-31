@extends('layouts.admin')

@section('title', 'Customer Analytics')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Customer Analytics</h1>
        <p class="text-[var(--color-steel)] mt-1">Customer growth and lifetime value</p>
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
    $customerStats = [
        [
            'label' => 'New Customers',
            'value' => number_format($totalCustomers),
            'change' => $customersChange,
            'icon' => 'fa-user-plus',
            'note' => 'vs previous period',
        ],
        [
            'label' => 'Previous Period',
            'value' => number_format($previousCustomers),
            'change' => null,
            'icon' => 'fa-clock-rotate-left',
            'note' => 'The window before this one',
        ],
        [
            'label' => 'Repeat Customers',
            'value' => number_format($repeatCustomers),
            'change' => null,
            'icon' => 'fa-recycle',
            'note' => 'Placed more than one order',
        ],
        [
            'label' => 'Avg Lifetime Value',
            'value' => format_currency((float) $avgLifetimeValue),
            'change' => null,
            'icon' => 'fa-heart',
            'note' => 'Per customer across all orders',
        ],
    ];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach($customerStats as $stat)
        @php $up = ($stat['change'] ?? 0) >= 0; @endphp
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-[var(--color-steel)]">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)] mt-1 truncate">{{ $stat['value'] }}</p>
                    @if($stat['change'] !== null)
                        <p class="text-xs mt-2 inline-flex items-center gap-1 {{ $up ? 'text-[#1B7A43]' : 'text-[var(--color-red)]' }}">
                            <i class="fa-solid {{ $up ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} text-[10px]"></i>
                            {{ number_format(abs($stat['change']), 1) }}%
                            <span class="text-[var(--color-steel)]">{{ $stat['note'] }}</span>
                        </p>
                    @else
                        <p class="text-xs mt-2 text-[var(--color-steel)]">{{ $stat['note'] }}</p>
                    @endif
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
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">New Customers Over Time</h2>
        <p class="text-xs text-[var(--color-steel)]">First-time customers registered each day</p>
    </div>
    <div style="height: 300px; width: 100%;">
        <canvas id="customersChart"></canvas>
    </div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="p-4 border-b hairline flex items-center justify-between">
        <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Top Customers by Spend</h2>
        <span class="text-xs text-[var(--color-steel)]">{{ count($topCustomersBySpend) }} customers</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Orders</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Total Spent</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Avg Order</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($topCustomersBySpend as $customer)
                    @php
                        $avgOrder = $customer['order_count'] > 0
                            ? $customer['total_spent'] / $customer['order_count']
                            : 0;
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center text-[var(--color-red)] font-semibold text-xs flex-shrink-0">{{ strtoupper(\Illuminate\Support\Str::of((string) $customer['name'])->substr(0, 1)->value() ?: '?') }}</div>
                                <span class="font-medium text-[var(--color-ink)]">{{ $customer['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-[var(--color-steel)]">{{ $customer['email'] }}</td>
                        <td class="px-6 py-3 text-right text-[var(--color-ink-soft)]">{{ number_format($customer['order_count']) }}</td>
                        <td class="px-6 py-3 text-right font-medium text-[var(--color-ink)]">{{ format_currency((float) $customer['total_spent']) }}</td>
                        <td class="px-6 py-3 text-right text-[var(--color-steel)]">{{ format_currency((float) $avgOrder) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-user-group text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No customer spend yet</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">Top spenders appear here once orders are placed.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('customersChart');
    if (!canvas) return;

    const style = getComputedStyle(document.documentElement);
    const css = (name, fallback) => (style.getPropertyValue(name) || fallback).trim();
    const steel = css('--color-steel', '#6b6f76');
    const red = css('--color-red', '#d6001c');
    const line = css('--color-line', '#dedcd4');

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: @json($labels ?? []),
            datasets: [{
                label: 'New Customers',
                data: @json($newCustomersData ?? []),
                backgroundColor: 'rgba(214, 0, 28, 0.55)',
                hoverBackgroundColor: red,
                borderColor: red,
                borderWidth: 1,
                borderRadius: 3,
                maxBarThickness: 28,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
                legend: { display: false },
            },
        },
    });
});
</script>
@endpush
@endsection