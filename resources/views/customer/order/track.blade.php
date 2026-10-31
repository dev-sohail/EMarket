@extends('layouts.store')
@section('title', 'Track Order')
@section('content')
{{-- Hero --}}
<section class="bg-[var(--color-ink)] text-white">
    <div class="max-w-2xl mx-auto px-4 py-12 sm:py-16">
        <span class="eyebrow text-[var(--color-red)]">Track Order</span>
        <h1 class="font-display text-3xl sm:text-4xl font-bold mt-2 leading-tight">Track Your Order</h1>
        <p class="text-white/70 mt-3">Enter your order number and either the email or phone number used at checkout.</p>
    </div>
</section>

<div class="max-w-2xl mx-auto px-4 py-12">
    @if(session('error'))
        <div class="mb-6 border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('order.track.result') }}" class="border hairline p-6 space-y-4 mb-10">
        @csrf
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Order Number *</label>
            <input type="text" name="order_number" value="{{ old('order_number') }}" required placeholder="e.g. ORD-2026-000001" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('order_number') border-[var(--color-red)] @enderror">
            @error('order_number') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Email used at checkout" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('email') border-[var(--color-red)] @enderror">
            @error('email') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Phone Number</label>
            <input type="text" name="phone" value="{{ old('phone') }}" placeholder="Phone used at checkout" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('phone') border-[var(--color-red)] @enderror">
            @error('phone') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary w-full">Track Order</button>
    </form>

    @isset($order)
        <div class="border hairline p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm text-[var(--color-steel)]">Order Number</p>
                    <p class="font-semibold text-[var(--color-ink)]">{{ $order->order_number }}</p>
                </div>
                <span class="inline-flex px-3 py-1 text-sm font-medium border hairline bg-[var(--color-paper-dim)] text-[var(--color-ink)]">{{ $order->status?->label() ?? 'Unknown' }}</span>
            </div>

             <div class="space-y-4 mb-6">
                @forelse($order->statusHistories()->orderBy('created_at')->get() as $history)
                    <div class="flex items-start gap-3">
                        <div class="w-2.5 h-2.5 rounded-full bg-[var(--color-red)] mt-1.5"></div>
                        <div>
                            <p class="text-sm font-medium text-[var(--color-ink)]">
                                @if($history->from_status)
                                    {{ ucfirst(str_replace('_', ' ', $history->from_status)) }} &rarr; {{ ucfirst(str_replace('_', ' ', $history->to_status)) }}
                                @else
                                    {{ ucfirst(str_replace('_', ' ', $history->to_status)) }}
                                @endif
                            </p>
                            <p class="text-xs text-[var(--color-steel)]">{{ $history->created_at->format('M d, Y H:i') }}</p>
                            @if($history->note)
                                <p class="text-sm text-[var(--color-steel)] mt-1 bg-[var(--color-paper-dim)] p-2 rounded">{{ $history->note }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[var(--color-steel)]">No status updates yet.</p>
                @endforelse
            </div>

            <div class="border-t hairline pt-4">
                <h3 class="text-sm font-semibold text-[var(--color-ink)] mb-3">Items</h3>
                <div class="space-y-3">
                    @foreach($order->items as $item)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-[var(--color-ink-soft)]">{{ $item->product->name ?? $item->product_name ?? 'Item' }} &times; {{ $item->quantity }}</span>
                            <span class="text-[var(--color-ink)] font-medium price">{{ format_currency($item->line_total ?? ($item->unit_price * $item->quantity)) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endisset
</div>
@endsection
