@extends('layouts.store')

@section('title', 'Return #' . $return->return_number)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.returns') }}" class="hover:text-[var(--color-red)]">Returns</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">#{{ $return->return_number }}</span>
    </nav>

    @php
        $statusConfig = [
            'requested' => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]', 'label' => 'Requested', 'icon' => 'fa-clock'],
            'under_review' => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'label' => 'Under Review', 'icon' => 'fa-magnifying-glass'],
            'approved' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'label' => 'Approved', 'icon' => 'fa-check'],
            'rejected' => ['bg' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'label' => 'Rejected', 'icon' => 'fa-xmark'],
            'returned' => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]', 'label' => 'Returned', 'icon' => 'fa-truck'],
            'completed' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'label' => 'Completed', 'icon' => 'fa-check-double'],
        ];
        $cfg = $statusConfig[$return->status?->value] ?? ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]', 'label' => $return->status?->label() ?? 'Unknown', 'icon' => 'fa-circle'];
    @endphp

    <div class="bg-white rounded-lg border hairline p-6 mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="font-display text-xl font-bold text-[var(--color-ink)]">Return #{{ $return->return_number }}</h1>
                <p class="text-sm text-[var(--color-steel)] mt-1">Order #{{ $return->order->order_number ?? 'N/A' }} &bull; {{ $return->created_at->format('M d, Y') }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 text-sm font-medium rounded-full {{ $cfg['bg'] }}">
                <i class="fa-solid {{ $cfg['icon'] }}"></i> {{ $cfg['label'] }}
            </span>
        </div>
        @if($return->description)
            <div class="mt-4 p-3 bg-[var(--color-paper-dim)] rounded-lg">
                <p class="text-sm text-[var(--color-steel)]">{{ $return->description }}</p>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-lg border hairline p-6 mb-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-4">Items</h2>
        <div class="space-y-3">
            @forelse($return->items as $item)
                <div class="flex items-center justify-between text-sm border-b last:border-0 pb-3 last:pb-0">
                    <div>
                        <p class="font-medium text-[var(--color-ink)]">{{ $item->orderItem->product->name ?? 'Product' }}</p>
                        <p class="text-xs text-[var(--color-steel)]">Qty: {{ $item->quantity }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)]">No items listed.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-lg border hairline p-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-4">Status History</h2>
        <div class="space-y-4">
            @forelse($return->statusHistories as $history)
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-[var(--color-red)]/10 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-check text-xs text-[var(--color-red)]"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-[var(--color-ink)]">
                            @if($history->from_status)
                                {{ $history->from_status->label() }} &rarr; {{ $history->to_status?->label() ?? 'Unknown' }}
                            @else
                                {{ $history->to_status?->label() ?? 'Unknown' }}
                            @endif
                        </p>
                        @if($history->note)
                            <p class="text-sm text-[var(--color-steel)] mt-0.5">{{ $history->note }}</p>
                        @endif
                        <p class="text-xs text-[var(--color-steel)] mt-0.5">{{ $history->created_at?->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)]">No status updates yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
