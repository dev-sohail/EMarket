@extends('layouts.store')

@section('title', 'My Orders')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" class="hover:text-[var(--color-red)]">Dashboard</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">My Orders</span>
    </nav>

    <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">My Orders</h1>

    {{-- Status Tabs --}}
    <div class="flex gap-2 overflow-x-auto pb-2 mb-6">
        <a href="{{ route('customer.orders') }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ !request('status') ? 'bg-[var(--color-red)] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] hover:bg-[var(--color-line)]' }}">All Orders</a>
        <a href="{{ route('customer.orders', ['status' => 'pending']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('status') == 'pending' ? 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">Pending</a>
        <a href="{{ route('customer.orders', ['status' => 'processing']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('status') == 'processing' ? 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">Processing</a>
        <a href="{{ route('customer.orders', ['status' => 'shipped']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('status') == 'shipped' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">Shipped</a>
        <a href="{{ route('customer.orders', ['status' => 'delivered']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('status') == 'delivered' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">Delivered</a>
        <a href="{{ route('customer.orders', ['status' => 'returned']) }}" class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap {{ request('status') == 'returned' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">Returned</a>
    </div>

    <div class="space-y-4">
        @forelse($orders ?? [] as $order)
            <div class="bg-white rounded-lg border hairline p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="font-semibold text-[var(--color-ink)]">Order #{{ $order->order_number }}</h3>
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full
                                {{ $order->status?->value === 'delivered' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : '' }}
                                {{ $order->status?->value === 'processing' ? 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]' : '' }}
                                {{ $order->status?->value === 'shipped' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : '' }}
                                {{ $order->status?->value === 'pending' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : '' }}
                                {{ $order->status?->value === 'cancelled' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : '' }}
                                {{ $order->status?->value === 'returned' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : '' }}">
                                {{ $order->status?->label() ?? 'Unknown' }}
                            </span>
                        </div>
                        <p class="text-sm text-[var(--color-steel)] mt-1">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-[var(--color-ink)]">{{ format_currency($order->grand_total) }}</p>
                        <p class="text-sm text-[var(--color-steel)]">{{ $order->items_count ?? $order->items->count() }} items</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 mt-4 pt-4 border-t hairline">
                    <div class="flex -space-x-2">
                        @foreach($order->items->take(3) as $item)
                            <div class="w-10 h-10 bg-[var(--color-paper-dim)] rounded-full border-2 border-white flex items-center justify-center">
                                <i class="fa-solid fa-image text-base text-[var(--color-steel)]"></i>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex-1 flex items-center justify-end gap-2">
                        <a href="{{ route('customer.orders.show', $order->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">View Details</a>
                        @if($order->status?->value === 'delivered' && setting('returns_enabled', false))
                            @php
                                $activeReturn = $order->returns()->whereNotIn('status', ['rejected', 'completed'])->first();
                            @endphp
                            @if($activeReturn)
                                <a href="{{ route('customer.returns.show', $activeReturn->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Show Return Request</a>
                            @else
                                <a href="{{ route('customer.returns.create', $order->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">Request Return</a>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-16 bg-white rounded-lg border hairline">
                <i class="fa-solid fa-file-lines text-5xl text-[var(--color-line)]"></i>
                <h3 class="text-lg font-medium text-[var(--color-ink)] mb-2">No orders found</h3>
                <p class="text-[var(--color-steel)] mb-4">Start shopping to see your orders here.</p>
                <a href="{{ route('shop') }}" class="bg-[var(--color-red)] text-white px-6 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Browse Products</a>
            </div>
        @endforelse
    </div>

    @if(isset($orders) && method_exists($orders, 'links'))
        <div class="mt-8">{{ $orders->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
