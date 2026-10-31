@extends('layouts.store')

@section('title', $campaign->name)

@section('content')
<div class="max-w-full mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('campaigns.index') }}" class="hover:text-[var(--color-red)] transition">Campaigns</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">{{ $campaign->name }}</span>
    </nav>

    <div class="bg-white rounded-lg border hairline overflow-hidden mb-8">
        <div class="p-6 bg-[var(--color-paper-dim)]">
            <h1 class="text-3xl font-bold text-[var(--color-ink)]">{{ $campaign->name }}</h1>
            <p class="text-sm text-[var(--color-steel)] mt-1">
                {{ $campaign->starts_at ? \Carbon\Carbon::parse($campaign->starts_at)->->format('M d, Y H:i') : '-' }} — {{ $campaign->ends_at ? \Carbon\Carbon::parse($campaign->ends_at)->->format('M d, Y H:i') : '-' }}
            </p>
            <p class="text-sm font-medium text-[var(--color-red)] mt-1">
                @if($campaign->type === 'percentage')
                    {{ $campaign->value }}% off
                @else
                    {{ format_currency($campaign->value) }} off
                @endif
            </p>
            {{-- discount_campaigns has no `description` column, nothing in the
                 admin form captures one, and the model has no accessor for it, so
                 this block could never render. --}}
        </div>
        <div class="p-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
            @forelse($campaign->products as $product)
                @include('components.store.product-card', [
                    'product' => $product,
                    'flashPrice' => $product->pivot->campaign_price ?? $product->final_price,
                    'flashOriginalPrice' => $product->base_price,
                    'badgeText' => 'Campaign',
                ])
            @empty
                <p class="text-[var(--color-steel)] col-span-full">No products in this campaign.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
