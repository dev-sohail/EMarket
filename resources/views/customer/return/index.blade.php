@extends('layouts.store')

@section('title', 'My Returns')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" class="hover:text-[var(--color-red)]">Dashboard</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">Returns</span>
    </nav>

    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)] mb-6">My Return Requests</h1>

    <div class="space-y-4">
        @forelse($returns ?? [] as $return)
            @php
                $statusConfig = [
                    'requested' => ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]', 'label' => 'Requested'],
                    'approved' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'label' => 'Approved'],
                    'rejected' => ['bg' => 'bg-[var(--color-red)]/10 text-[var(--color-red)]', 'label' => 'Rejected'],
                    'completed' => ['bg' => 'bg-[#1B7A43]/10 text-[#1B7A43]', 'label' => 'Completed'],
                ];
                $cfg = $statusConfig[$return->status?->value] ?? ['bg' => 'bg-[var(--color-paper-dim)] text-[var(--color-steel)]', 'label' => $return->status?->label() ?? 'Unknown'];
            @endphp
            <div class="bg-white rounded-lg border hairline p-6 hover:shadow-sm transition">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="font-semibold text-[var(--color-ink)]">Return #{{ $return->return_number }}</h3>
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded-full {{ $cfg['bg'] }}">{{ $cfg['label'] }}</span>
                        </div>
                        <p class="text-sm text-[var(--color-steel)] mt-1">Order #{{ $return->order->order_number ?? 'N/A' }} &bull; {{ $return->created_at->format('M d, Y') }}</p>
                    </div>
                    <a href="{{ route('customer.returns.show', $return) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">View Details &rarr;</a>
                </div>
                @if($return->description)
                    <div class="mt-3 text-sm text-[var(--color-steel)]">
                        <p><span class="font-medium text-[var(--color-ink)]">Reason:</span> {{ Str::limit($return->description, 120) }}</p>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-center py-16 bg-white rounded-lg border hairline">
                <i class="fa-solid fa-rotate-left text-5xl text-[var(--color-line)] mb-3"></i>
                <h3 class="text-lg font-medium text-[var(--color-ink)] mb-2">No return requests</h3>
                <p class="text-[var(--color-steel)]">You haven't submitted any return requests yet.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
