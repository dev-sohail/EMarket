@extends('layouts.store')

@section('title', $flashSale->name)

@section('content')
<div class="max-w-full mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('flash-sales.index') }}" class="hover:text-[var(--color-red)] transition">Flash Sales</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">{{ $flashSale->name }}</span>
    </nav>

    <div class="bg-white rounded-lg border hairline overflow-hidden mb-8">
        <div class="p-6 bg-[var(--color-paper-dim)]">
            <h1 class="text-3xl font-bold text-[var(--color-ink)]">{{ $flashSale->name }}</h1>
            <p class="text-sm text-[var(--color-steel)] mt-1">
                {{ $flashSale->starts_at ? \Carbon\Carbon::parse($flashSale->starts_at)->->format('M d, Y H:i') : '-' }} — {{ $flashSale->ends_at ? \Carbon\Carbon::parse($flashSale->ends_at)->->format('M d, Y H:i') : '-' }}
            </p>
        </div>
        <div class="p-6 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
            @forelse($flashSale->products as $product)
                @include('components.store.product-card', [
                    'product' => $product,
                    'flashPrice' => $product->pivot->flash_price ?? $flashSale->sale_price,
                    'flashOriginalPrice' => $product->base_price,
                    'badgeText' => 'Flash Sale',
                ])
            @empty
                <p class="text-[var(--color-steel)] col-span-full">No products in this flash sale.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
