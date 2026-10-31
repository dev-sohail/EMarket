@extends('layouts.store')

@section('title', 'Campaigns')

@section('content')
<div class="max-w-full mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-[var(--color-ink)] mb-2">Campaigns</h1>
    <p class="text-[var(--color-steel)] mb-8">Special offers and limited-time promotions.</p>

    @forelse($campaigns as $campaign)
        <div class="bg-white rounded-lg border hairline overflow-hidden mb-8">
            <div class="p-6 flex items-center justify-between bg-[var(--color-paper-dim)]">
                <div>
                    <h2 class="text-2xl font-bold text-[var(--color-ink)]">{{ $campaign->name }}</h2>
                    <p class="text-sm text-[var(--color-steel)] mt-1">
                        {{ $campaign->starts_at?->format('M d, Y H:i') }} — {{ $campaign->ends_at?->format('M d, Y H:i') }}
                    </p>
                    <p class="text-sm font-medium text-[var(--color-red)] mt-1">
                        @if($campaign->type === 'percentage')
                            {{ $campaign->value }}% off
                        @else
                            {{ format_currency($campaign->value) }} off
                        @endif
                    </p>
                </div>
                <a href="{{ route('campaigns.show', $campaign) }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                    View Deal
                </a>
            </div>
            <div class="p-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($campaign->products->take(4) as $product)
                    @include('components.store.product-card', [
                        'product' => $product,
                        'flashPrice' => $product->pivot->campaign_price ?? $product->final_price,
                        'flashOriginalPrice' => $product->base_price,
                        'badgeText' => 'Campaign',
                    ])
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-lg border hairline p-12 text-center text-[var(--color-steel)]">
            No active campaigns right now. Check back soon!
        </div>
    @endforelse
</div>
@endsection
