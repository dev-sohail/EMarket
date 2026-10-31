@extends('layouts.store')

@section('title', 'Flash Sales')

@section('content')
<div class="max-w-full mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-[var(--color-ink)] mb-2">Flash Sales</h1>
    <p class="text-[var(--color-steel)] mb-8">Limited-time offers. Grab them before they’re gone!</p>

    @forelse($flashSales as $sale)
        <div class="bg-white rounded-lg border hairline overflow-hidden mb-8">
            <div class="p-6 flex items-center justify-between bg-[var(--color-paper-dim)]">
                <div>
                    <h2 class="text-2xl font-bold text-[var(--color-ink)]">{{ $sale->name }}</h2>
                    <p class="text-sm text-[var(--color-steel)] mt-1">
                        {{ $sale->starts_at?->format('M d, Y H:i') }} — {{ $sale->ends_at?->format('M d, Y H:i') }}
                    </p>
                </div>
                <a href="{{ route('flash-sales.show', $sale) }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                    View Deals
                </a>
            </div>
            <div class="p-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($sale->products->take(4) as $product)
                    @include('components.store.product-card', [
                        'product' => $product,
                        'flashPrice' => $product->pivot->flash_price ?? $sale->sale_price,
                        'flashOriginalPrice' => $product->base_price,
                        'badgeText' => 'Flash Sale',
                    ])
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-white rounded-lg border hairline p-12 text-center text-[var(--color-steel)]">
            No active flash sales right now. Check back soon!
        </div>
    @endforelse
</div>
@endsection
