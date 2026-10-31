@extends('layouts.admin')

@section('title', 'Payment Methods')

@php
    $sortBy = request('sort_by', 'sort_order');
    $sortDir = request('sort_dir', 'asc');

    /**
     * Sortable column header. Each click carries the other active filters
     * forward and flips the direction only when the same column is clicked
     * again, so choosing a status never silently discards the search.
     */
    $sortLink = function (string $column) use ($sortBy, $sortDir) {
        $direction = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
        $query = array_merge(request()->query(), ['sort_by' => $column, 'sort_dir' => $direction]);

        return request()->fullUrlWithQuery($query);
    };

    $sortIcon = function (string $column) use ($sortBy, $sortDir) {
        // There is no neutral `fa-arrow-down-up` in Font Awesome Free 7 — it
        // was removed, so the unsorted columns would render a blank gap.
        if ($sortBy !== $column) {
            return 'fa-sort-down text-[var(--color-steel)]/40';
        }

        return $sortDir === 'asc'
            ? 'fa-sort-up text-[var(--color-red)]'
            : 'fa-sort-down text-[var(--color-red)]';
    };

    $activeFilterCount = collect(['search', 'status', 'type'])
        ->reject(fn ($key) => blank(request($key)))
        ->count();
@endphp

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Payment Methods</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            Cash on Delivery and Online are the two supported methods. Names, instructions and availability are configurable.
        </p>
    </div>
    @if($activeFilterCount > 0)
        <a href="{{ route('admin.payment-methods.index') }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition flex items-center gap-2">
            <i class="fa-solid fa-xmark"></i>
            Clear {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }}
        </a>
    @endif
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @php
        $summaryCards = [
            ['label' => 'Total Methods', 'value' => $stats['total'] ?? 0, 'icon' => 'fa-money-bill-transfer', 'tone' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]'],
            ['label' => 'Active', 'value' => $stats['active'] ?? 0, 'icon' => 'fa-circle-check', 'tone' => 'bg-[#1B7A43]/10 text-[#1B7A43]'],
            ['label' => 'Inactive', 'value' => $stats['inactive'] ?? 0, 'icon' => 'fa-circle-pause', 'tone' => 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]'],
            ['label' => 'Stripe Enabled', 'value' => $stats['stripe_ready'] ?? 0, 'icon' => 'fa-stripe', 'family' => 'fa-brands', 'tone' => 'bg-[#635BFF]/10 text-[#635BFF]'],
        ];
    @endphp
    @foreach($summaryCards as $card)
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ $card['value'] }}</p>
                </div>
                <div class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0 {{ $card['tone'] }}">
                    <i class="{{ $card['family'] ?? 'fa-solid' }} {{ $card['icon'] }}"></i>
                </div>
            </div>
        </div>
    @endforeach
</div>

<form method="GET" action="{{ route('admin.payment-methods.index') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-4">
    @if(request()->query('sort_by'))
        <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
        <input type="hidden" name="sort_dir" value="{{ request('sort_dir', 'asc') }}">
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[240px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name, code or instructions…"
                   class="w-full border hairline rounded-lg bg-[var(--color-paper)] pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
        </div>

        <select name="status" data-auto-submit aria-label="Availability" class="border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="">All Statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active only</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive only</option>
        </select>

        <select name="type" data-auto-submit aria-label="Type" class="border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="">All Types</option>
            <option value="cod" @selected(request('type') === 'cod')>COD</option>
            <option value="online" @selected(request('type') === 'online')>Online</option>
        </select>

        <button type="submit" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition flex items-center gap-2">
            <i class="fa-solid fa-magnifying-glass"></i>
            Search
        </button>
    </div>
</form>

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">
                        <a href="{{ $sortLink('name') }}" class="inline-flex items-center gap-1.5 hover:text-[var(--color-ink)]">
                            Method
                            <i class="fa-solid fa-xs {{ $sortIcon('name') }}"></i>
                        </a>
                    </th>
                    <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">
                        <a href="{{ $sortLink('code') }}" class="inline-flex items-center gap-1.5 hover:text-[var(--color-ink)]">
                            Code
                            <i class="fa-solid fa-xs {{ $sortIcon('code') }}"></i>
                        </a>
                    </th>
                    <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Customer Instructions</th>
                    <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Type</th>
                    <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Gateway</th>
                    <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">
                        <a href="{{ $sortLink('sort_order') }}" class="inline-flex items-center gap-1.5 hover:text-[var(--color-ink)]">
                            Sort Order
                            <i class="fa-solid fa-xs {{ $sortIcon('sort_order') }}"></i>
                        </a>
                    </th>
                    <th scope="col" class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Availability</th>
                    <th scope="col" class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($paymentMethods as $method)
                    <tr class="hover:bg-[var(--color-paper-dim)]/40">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-[var(--color-ink)]">{{ $method->name }}</p>
                            @unless($method->is_active)
                                <p class="text-xs text-[var(--color-steel)] mt-0.5">Hidden from checkout</p>
                            @endunless
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center rounded-md bg-[var(--color-paper-dim)] px-2 py-1 font-mono text-xs text-[var(--color-ink-soft)]">{{ $method->code }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @if(blank($method->description))
                                <span class="text-sm text-[var(--color-steel)] italic">No instructions shown to customers</span>
                            @else
                                <p class="text-sm text-[var(--color-steel)] max-w-sm">{{ \Illuminate\Support\Str::limit($method->description, 80) }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            {{-- Literal class strings only: Tailwind v4 cannot see through
                                 an interpolated ternary reliably and would drop the badge. --}}
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full {{ $method->is_cod ? 'bg-[var(--color-red)]/10 text-[var(--color-red-dark)]' : 'bg-[#635BFF]/10 text-[#635BFF]' }}">
                                <i class="fa-solid {{ $method->is_cod ? 'fa-money-bill-wave' : 'fa-globe' }}"></i>
                                {{ $method->is_cod ? 'Cash on Delivery' : 'Online' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($method->is_cod)
                                <span class="text-sm text-[var(--color-steel)]">—</span>
                            @elseif($method->stripe_enabled)
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[#1B7A43]/10 text-[#1B7A43]">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Stripe · {{ $method->stripe_sandbox ? 'Test' : 'Live' }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-steel)]">
                                    <i class="fa-solid fa-circle-minus"></i>
                                    Manual only
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-medium text-[var(--color-ink)]">{{ $method->sort_order }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @if($method->is_active)
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[#1B7A43]/10 text-[#1B7A43]">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-steel)]">
                                    <i class="fa-solid fa-circle-pause"></i>
                                    Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.payment-methods.edit', $method->getKey()) }}" class="inline-flex items-center gap-1.5 rounded-lg border hairline px-3 py-1.5 text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
                                <i class="fa-solid fa-pen"></i>
                                Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-16 text-center">
                            <div class="w-14 h-14 rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mx-auto mb-4">
                                <i class="fa-solid fa-money-bill-transfer text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">
                                {{ $activeFilterCount > 0 ? 'No payment methods match these filters' : 'No payment methods found' }}
                            </p>
                            @if($activeFilterCount > 0)
                                <a href="{{ route('admin.payment-methods.index') }}" class="inline-flex items-center gap-1.5 mt-4 text-sm font-medium text-[var(--color-red)] hover:underline">
                                    <i class="fa-solid fa-xmark"></i>
                                    Clear all filters
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
