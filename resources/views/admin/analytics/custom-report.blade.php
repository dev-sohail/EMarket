@extends('layouts.admin')

@section('title', 'Custom Report Builder')

@section('header')
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)]">Custom Report Builder</h1>
        <p class="text-[var(--color-steel)] mt-1">Build and export custom analytics reports</p>
    </div>
    <a href="{{ route('admin.analytics.export', array_merge(['report_type' => 'custom'], request()->query())) }}"
       class="px-4 py-2 bg-[var(--color-red)] text-white rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition-colors">
        <i class="fa-solid fa-download mr-2"></i>Export
    </a>
</div>
@endsection

@section('content')
@include('admin.analytics._nav')

<div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Report Type</label>
            <select name="report_type" class="w-full border border-[var(--color-steel-light)] rounded-lg px-3 py-2 text-sm">
                <option value="sales" {{ $reportType == 'sales' ? 'selected' : '' }}>Sales</option>
                <option value="products" {{ $reportType == 'products' ? 'selected' : '' }}>Products</option>
                <option value="customers" {{ $reportType == 'customers' ? 'selected' : '' }}>Customers</option>
                <option value="orders" {{ $reportType == 'orders' ? 'selected' : '' }}>Orders</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Date From</label>
            <input type="date" name="date_from" value="{{ $startDate?->format('Y-m-d') }}" class="w-full border border-[var(--color-steel-light)] rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Date To</label>
            <input type="date" name="date_to" value="{{ $endDate?->format('Y-m-d') }}" class="w-full border border-[var(--color-steel-light)] rounded-lg px-3 py-2 text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Group By</label>
            <select name="group_by" class="w-full border border-[var(--color-steel-light)] rounded-lg px-3 py-2 text-sm">
                @foreach($availableGroupBy as $option)
                    <option value="{{ $option }}" {{ $groupBy == $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </div>

        <div class="pt-9">
            <button type="submit" class="w-full px-4 py-2 bg-[var(--color-red)] text-white rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)]">
                Generate Report
            </button>
        </div>
    </form>

    <div class="mt-4">
        <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Metrics</label>
        <div class="flex flex-wrap gap-3">
            @foreach($availableMetrics as $metric)
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="metrics[]" value="{{ $metric }}"
                        {{ in_array($metric, $metrics) ? 'checked' : '' }}
                        class="w-4 h-4 text-[var(--color-red)] border-[var(--color-steel-light)] rounded focus:ring-[var(--color-red)] toggle-metric">
                    <span class="text-sm text-[var(--color-ink)]">{{ str_replace('_', ' ', ucfirst($metric)) }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>

<div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
    <h3 class="font-display text-lg font-semibold text-[var(--color-ink)] mb-4">Report Results</h3>

    @if(empty($reportData))
        <p class="text-sm text-[var(--color-steel)]">No data to display. Configure your report above and click "Generate Report".</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="customReportTable">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        @php
                            $headers = collect($reportData)->isNotEmpty() ? array_keys((array) $reportData[0]) : [];
                        @endphp
                        @foreach($headers as $header)
                            <th class="px-4 py-2 text-left text-xs font-medium text-[var(--color-steel)] uppercase">{{ str_replace('_', ' ', ucfirst($header)) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportData as $row)
                        <tr class="border-b hairline">
                            @foreach((array) $row as $value)
                                <td class="px-4 py-2 text-[var(--color-ink)]">{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
