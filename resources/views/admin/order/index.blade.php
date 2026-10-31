@extends('layouts.admin')

@section('title', 'Orders')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Orders</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            {{ $orders->total() }} order{{ $orders->total() === 1 ? '' : 's' }} match the current filters
        </p>
    </div>
    <a href="{{ route('admin.reports.orders') }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition flex items-center gap-2">
        <i class="fa-solid fa-chart-column"></i>
        Order Reports
    </a>
</div>
@endsection

@section('content')
@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;

    // Every tab carries the other active filters forward. Previously each one
    // was built from `status` alone, so choosing a status silently discarded
    // the search and the date range.
    $tabQuery = request()->except(['status', 'page']);

    /**
     * Badge tone per status. The old markup used five conditional class
     * expressions with no fallback, so an order in any other status rendered
     * as unstyled text.
     */
    $statusTone = fn (OrderStatus $status): string => match ($status) {
        OrderStatus::Pending => 'bg-[#F59E0B]/10 text-[#B45309]',
        OrderStatus::Confirmed => 'bg-[#2563EB]/10 text-[#2563EB]',
        OrderStatus::Processing => 'bg-[var(--color-red)]/10 text-[var(--color-red-dark)]',
        OrderStatus::ReadyToShip, OrderStatus::Shipped, OrderStatus::OutForDelivery => 'bg-[#0E7490]/10 text-[#0E7490]',
        OrderStatus::Delivered, OrderStatus::Completed => 'bg-[#1B7A43]/10 text-[#1B7A43]',
        OrderStatus::Returned => 'bg-[#7C3AED]/10 text-[#7C3AED]',
        OrderStatus::Cancelled => 'bg-[var(--color-steel)]/15 text-[var(--color-steel)]',
    };

    $paymentTone = fn (PaymentStatus $status): string => match ($status) {
        PaymentStatus::Paid => 'bg-[#1B7A43]/10 text-[#1B7A43]',
        PaymentStatus::Pending => 'bg-[#F59E0B]/10 text-[#B45309]',
        PaymentStatus::Failed => 'bg-[var(--color-red)]/10 text-[var(--color-red)]',
        PaymentStatus::Refunded => 'bg-[#7C3AED]/10 text-[#7C3AED]',
    };

    $activeFilterCount = collect($filters ?? [])
        ->reject(fn ($value) => $value === null || $value === '')
        ->count();
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @php
        $summaryCards = [
            ['label' => 'Total Orders', 'value' => number_format($stats['total'] ?? 0), 'icon' => 'fa-receipt', 'tone' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'query' => []],
            ['label' => 'Awaiting Fulfilment', 'value' => number_format(($stats['pending'] ?? 0) + ($stats['confirmed'] ?? 0) + ($stats['processing'] ?? 0)), 'icon' => 'fa-box-open', 'tone' => 'bg-[#F59E0B]/10 text-[#B45309]', 'query' => ['status' => 'processing']],
            ['label' => 'Revenue', 'value' => format_currency($stats['revenue'] ?? 0), 'icon' => 'fa-sack-dollar', 'tone' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'query' => []],
            ['label' => 'Average Order', 'value' => format_currency($stats['average_order'] ?? 0), 'icon' => 'fa-chart-simple', 'tone' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'query' => []],
        ];
    @endphp
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

<form method="GET" action="{{ route('admin.orders.index') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-4">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[240px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-steel)] text-sm"></i>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by order #, customer name, email, phone…"
                   class="w-full border hairline rounded-lg pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
        </div>

        <select name="payment_status" data-auto-submit aria-label="Payment status" class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
            <option value="">All Payments</option>
            @foreach(PaymentStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected(request('payment_status') === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>

        <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="From date" title="Placed on or after"
               class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
        <span class="text-xs text-[var(--color-steel)]">to</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="To date" title="Placed on or before"
               class="border hairline rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">

        <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Search</button>

        @if($activeFilterCount > 0)
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]">Clear</a>
        @endif
    </div>
</form>

{{-- One tab per status, all ten of them: the list previously offered six,
     leaving Confirmed, Ready to Ship, Out for Delivery, Completed and Returned
     reachable only by hand-editing the URL. --}}
<nav data-tabs class="flex gap-2 overflow-x-auto pb-2 mb-6">
    <a href="{{ route('admin.orders.index', $tabQuery) }}"
       @if(request('status') === null) aria-current="page" @endif
       class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition {{ request('status') === null ? 'bg-[var(--color-ink)] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] hover:bg-[var(--color-line)]' }}">
        All <span class="opacity-70">{{ $stats['total'] ?? 0 }}</span>
    </a>
    @foreach(OrderStatus::cases() as $case)
        <a href="{{ route('admin.orders.index', $tabQuery + ['status' => $case->value]) }}"
           @if(request('status') === $case->value) aria-current="page" @endif
           class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition {{ request('status') === $case->value ? 'bg-[var(--color-ink)] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] hover:bg-[var(--color-line)]' }}">
            {{ $case->label() }}
            @if(($stats[$case->value] ?? 0) > 0)
                <span class="opacity-70">{{ number_format($stats[$case->value]) }}</span>
            @endif
        </a>
    @endforeach
</nav>

@if($activeFilterCount > 0)
    <div class="bg-[var(--color-paper-dim)] border-l-2 border-[var(--color-red)] rounded-r-lg px-4 py-3 mb-4 text-sm text-[var(--color-ink-soft)]">
        <i class="fa-solid fa-filter text-[var(--color-red)] mr-1"></i>
        {{ $activeFilterCount }} filter{{ $activeFilterCount === 1 ? '' : 's' }} applied &mdash; the counts on each tab always describe the whole order book.
    </div>
@endif

<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Order</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Customer</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Items</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Total</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Payment</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Date</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($orders ?? [] as $order)
                    @php
                        $customerName = $order->customer?->full_name;
                        $isGuest = $customerName === null;
                    @endphp
                    <tr class="hover:bg-[var(--color-paper-dim)] transition-colors">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.orders.show', $order->getKey()) }}" class="font-medium text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">
                                #{{ $order->order_number }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($isGuest)
                                <span class="text-[var(--color-steel)]">Guest checkout</span>
                                <span class="inline-flex ml-2 px-2 py-0.5 text-[10px] font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-steel)]">Guest</span>
                            @else
                                <span class="text-[var(--color-ink)]">{{ $customerName }}</span>
                                @if($order->customer?->email)
                                    <span class="block text-xs text-[var(--color-steel)] truncate max-w-[16rem]">{{ $order->customer->email }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">
                            {{ $order->items_count ?? $order->items->count() }}
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)] price">{{ format_currency($order->grand_total) }}</td>
                        <td class="px-6 py-4">
                            @if($order->payment_status)
                                <span data-payment="{{ $order->payment_status->value }}" class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $paymentTone($order->payment_status) }}">
                                    {{ $order->payment_status->label() }}
                                </span>
                            @else
                                <span class="text-xs text-[var(--color-steel)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($order->status)
                                <span data-status="{{ $order->status->value }}" class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $statusTone($order->status) }}">
                                    {{ $order->status->label() }}
                                </span>
                            @else
                                <span class="text-xs text-[var(--color-steel)]">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)] whitespace-nowrap">
                            <span class="block">{{ $order->created_at->format('M d, Y') }}</span>
                            <span class="block text-xs">{{ $order->created_at->format('H:i') }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.orders.show', $order->getKey()) }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-[var(--color-paper-dim)] flex items-center justify-center mb-3">
                                <i class="fa-solid fa-receipt text-[var(--color-steel)]"></i>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">No orders match these filters</p>
                            <p class="text-sm text-[var(--color-steel)] mt-1">
                                @if($activeFilterCount > 0)
                                    <a href="{{ route('admin.orders.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear the filters</a> to see the whole order book.
                                @else
                                    Orders will appear here as soon as the first one is placed.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($orders, 'links'))
        <div class="p-4 border-t hairline">{{ $orders->links() }}</div>
    @endif
</div>
@endsection