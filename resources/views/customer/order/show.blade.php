@extends('layouts.store')

@section('title', 'Order #' . ($order->order_number ?? ''))

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" class="hover:text-[var(--color-red)]">Dashboard</a>
        <span>/</span>
        <a href="{{ route('customer.orders') }}" class="hover:text-[var(--color-red)]">Orders</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">#{{ $order->order_number ?? '' }}</span>
    </nav>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Order #{{ $order->order_number ?? '' }}</h1>
        <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full
            {{ $order->status?->value === 'delivered' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : '' }}
            {{ $order->status?->value === 'processing' ? 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]' : '' }}
            {{ $order->status?->value === 'shipped' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : '' }}
            {{ $order->status?->value === 'pending' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : '' }}
            {{ $order->status?->value === 'cancelled' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : '' }}
            {{ $order->status?->value === 'returned' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : '' }}">
            {{ $order->status?->label() ?? 'Unknown' }}
        </span>
    </div>

    {{-- Status Timeline --}}
    <div class="bg-white rounded-lg border hairline p-6 mb-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-4">Order Status</h2>
        @php
            $statuses = ['pending', 'processing', 'shipped', 'delivered'];
            $currentIndex = array_search($order->status?->value ?? 'pending', $statuses);
            if ($currentIndex === false && $order->status?->value === 'returned') {
                $currentIndex = 3;
            }
        @endphp
        <div class="flex items-center justify-between">
            @foreach($statuses as $index => $status)
                <div class="flex-1 flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $index <= $currentIndex ? 'bg-[#1B7A43] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]' }}">
                        @if($index < $currentIndex)
                            <i class="fa-solid fa-check text-base"></i>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>
                    <span class="text-xs mt-2 text-center {{ $index <= $currentIndex ? 'text-[#1B7A43] font-medium' : 'text-[var(--color-steel)]' }}">{{ ucfirst($status) }}</span>
                </div>
                @if(!$loop->last)
                    <div class="flex-1 h-0.5 {{ $index < $currentIndex ? 'bg-[#1B7A43]' : 'bg-[var(--color-paper-dim)]' }} mx-2 mt-[-12px]"></div>
                @endif
            @endforeach
        </div>
        @if($order->status?->value === 'returned')
            <div class="mt-4 p-3 bg-[var(--color-red)]/10 border border-[var(--color-red)]/20 rounded">
                <p class="text-sm text-[var(--color-red)] font-medium">Return Requested</p>
                <p class="text-xs text-[var(--color-steel)]">This order has an active return request.</p>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Order Items --}}
        <div class="lg:col-span-2 bg-white rounded-lg border hairline p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Order Items</h2>
            <div class="space-y-4">
                @foreach($order->items ?? [] as $item)
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-[var(--color-paper-dim)] rounded-lg flex-shrink-0 flex items-center justify-center">
                            @if($item->product && $item->product->primary_image)
                                <img src="{{ asset('storage/' . $item->product->primary_image) }}" alt="{{ $item->product->name }}" class="w-full h-full object-cover rounded-lg" loading="lazy">
                            @else
                                <i class="fa-solid fa-image text-2xl text-[var(--color-line)]"></i>
                            @endif
                        </div>
                        <div class="flex-1">
                            <h3 class="font-medium text-[var(--color-ink)]">{{ $item->product->name ?? 'Product' }}</h3>
                            @if($item->variant)
                                <p class="text-sm text-[var(--color-steel)]">{{ $item->variant->name }}</p>
                            @endif
                            <p class="text-sm text-[var(--color-steel)]">Qty: {{ $item->quantity }}</p>
                        </div>
                        <p class="font-medium text-[var(--color-ink)]">{{ format_currency($item->line_total ?? $item->unit_price * $item->quantity) }}</p>
                    </div>
                @endforeach
            </div>

            <div class="border-t hairline mt-4 pt-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-[var(--color-steel)]">Subtotal</span>
                    <span>{{ format_currency($order->subtotal ?? 0) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-[var(--color-steel)]">Shipping</span>
                    <span>{{ format_currency($order->shipping_cost ?? 0) }}</span>
                </div>
                @if($order->discount ?? 0 > 0)
                    <div class="flex justify-between text-sm text-[#1B7A43]">
                        <span>Discount</span>
                        <span>-{{ format_currency($order->discount) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-lg font-bold border-t hairline pt-2">
                    <span class="text-[var(--color-ink)]">Total</span>
                    <span class="text-[var(--color-ink)]">{{ format_currency($order->grand_total ?? 0) }}</span>
                </div>
            </div>

            {{-- Status History --}}
            @if($order->statusHistories->count() > 0)
            <div class="bg-white rounded-lg border hairline p-6 mt-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Status History</h2>
                <div class="space-y-4">
                    @foreach($order->statusHistories as $history)
                        <div class="flex items-start gap-3">
                            <div class="w-2.5 h-2.5 rounded-full bg-[var(--color-red)] mt-1.5 flex-shrink-0"></div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-[var(--color-ink)]">{{ ucwords(str_replace('_', ' ', $history->to_status)) }}</p>
                                <p class="text-xs text-[var(--color-steel)]">{{ $history->created_at->format('M d, Y H:i') }}</p>
                                @if($history->note)
                                    <p class="text-sm text-[var(--color-steel)] mt-1 bg-[var(--color-paper-dim)] p-2 rounded">{{ $history->note }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Order Info --}}
        <div class="space-y-6">
            {{-- Customer Info --}}
            <div class="bg-white rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-3">Customer Information</h2>
                <div class="text-sm text-[var(--color-steel)] space-y-1">
                    @php
                        $customer = $order->customer;
                        $user = $order->user;
                        $name = $customer ? trim($customer->first_name.' '.$customer->last_name) : ($user?->name ?? 'Customer');
                        $email = $customer?->email ?: $user?->email;
                        $phone = $customer?->phone ?: $user?->phone;
                    @endphp
                    <p class="font-medium text-[var(--color-ink)]">{{ $name }}</p>
                    @if($email)
                        <p>Email: {{ $email }}</p>
                    @endif
                    @if($phone)
                        <p>Phone: {{ $phone }}</p>
                    @endif
                </div>
            </div>

            {{-- Shipping Address --}}
            <div class="bg-white rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-3">Shipping Address</h2>
                @if($order->shippingAddress ?? null)
                    <div class="text-sm text-[var(--color-steel)] space-y-1">
                        <p>{{ $order->shippingAddress->full_name }}</p>
                        <p>{{ $order->shippingAddress->address_line_1 }}</p>
                        @if($order->shippingAddress->address_line_2)
                            <p>{{ $order->shippingAddress->address_line_2 }}</p>
                        @endif
                         <p>{{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }} {{ $order->shippingAddress->postal_code }}</p>
                    </div>
                @else
                    <p class="text-sm text-[var(--color-steel)]">No shipping address</p>
                @endif
            </div>

            {{-- Payment Info --}}
            <div class="bg-white rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-3">Payment Information</h2>
                <div class="text-sm text-[var(--color-steel)] space-y-1">
                    <p>Method: {{ $order->payment_method_label }}</p>
                    <p>Status: <span class="{{ $order->payment_status?->value === 'paid' ? 'text-[#1B7A43]' : 'text-[var(--color-red-dark)]' }}">{{ $order->payment_status?->label() ?? 'Pending' }}</span></p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="bg-white rounded-lg border hairline p-6 space-y-3">
                @if($order->status?->value === 'delivered' && setting('returns_enabled', false))
                    @php
                        $activeReturn = $order->returns()->whereNotIn('status', ['rejected', 'completed'])->first();
                    @endphp
                    @if($activeReturn)
                        <a href="{{ route('customer.returns.show', $activeReturn->getKey()) }}" class="block w-full text-center border border-[var(--color-red)] text-[var(--color-red)] py-2 rounded-lg hover:bg-[var(--color-red)]/5 transition">Show Return Request</a>
                    @else
                        <a href="{{ route('customer.returns.create', $order->getKey()) }}" class="block w-full text-center border border-[var(--color-red)] text-[var(--color-red)] py-2 rounded-lg hover:bg-[var(--color-red)]/5 transition">Request Return</a>
                    @endif
                @endif
                <a href="{{ route('customer.orders') }}" class="block w-full text-center border hairline text-[var(--color-ink)] py-2 rounded-lg hover:bg-[var(--color-paper-dim)] transition">Back to Orders</a>
            </div>
        </div>
    </div>
</div>
@endsection
