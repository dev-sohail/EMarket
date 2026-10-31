@extends('layouts.store')

@section('title', 'Home')

@section('hero')
<section class="bg-[var(--color-ink)] text-white">
    <div class="max-w-full mx-auto px-4 py-16 sm:py-24 grid lg:grid-cols-12 gap-8 items-center">
        <div class="lg:col-span-7">
            <span class="eyebrow text-[var(--color-red)]">{{ $homepageBanners?->subtitle ?: '' }}</span>
            <h1 class="font-display text-4xl sm:text-6xl font-bold mt-3 leading-[1.05] tracking-tight">
                {!! nl2br(e($homepageBanners?->title ?: "")) !!}
            </h1>
            <p class="text-lg text-white/70 mt-5 max-w-xl">{{ $homepageBanners?->description ?: '' }}</p>
            <div class="flex flex-col sm:flex-row gap-4 mt-8">
                @if($homepageBanners?->cta_text)
                    @if($homepageBanners->url)
                        <a href="{{ $homepageBanners->url }}" class="btn btn-primary">{{ $homepageBanners->cta_text }}</a>
                    @else
                        <span class="btn btn-primary">{{ $homepageBanners->cta_text }}</span>
                    @endif
                @else
                    <a href="{{ route('shop') }}" class="btn btn-primary"></a>
                @endif
                @if($homepageBanners?->cta_text_2)
                    @if($homepageBanners->url_2)
                        <a href="{{ $homepageBanners->url_2 }}" class="btn btn-outline text-white">{{ $homepageBanners->cta_text_2 }}</a>
                    @else
                        <span class="btn btn-outline text-white">{{ $homepageBanners->cta_text_2 }}</span>
                    @endif
                @endif
            </div>
        </div>
        <div class="lg:col-span-5 hidden lg:block">
            <div class="border border-white/15 aspect-square flex items-center justify-center relative overflow-hidden">
                @if($homepageBanners?->image)
                    <img src="{{ asset('storage/' . $homepageBanners->image) }}" alt="{{ $homepageBanners->title }}" class="w-full">
                @else
                    <span class="font-display text-white/10 text-[10rem] font-bold leading-none select-none"></span>
                    <span class="absolute bottom-4 right-4 text-xs text-white/50 tracking-wide"></span>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection

@section('content')

{{-- Categories Grid --}}
@if($categories->isNotEmpty())
<section class="max-w-full mx-auto px-4 py-12">
    <h2 class="font-display text-2xl font-bold text-[var(--color-ink)] mb-6">Shop by Category</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-px bg-[var(--color-line)] border hairline">
        @foreach($categories->take(6) as $category)
            <a href="{{ route('category.show', $category->slug) }}" class="group bg-[var(--color-paper)] hover:bg-[var(--color-ink)] transition p-6 text-center">
                <div class="w-14 h-14 mx-auto flex items-center justify-center mb-3 overflow-hidden">
                    @if($category->image)
                        <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}" class="w-14 h-14 object-cover rounded" loading="lazy">
                    @else
                        <i class="fa-solid fa-box text-2xl text-[var(--color-red)] group-hover:text-white transition"></i>
                    @endif
                </div>
                <h3 class="font-medium text-sm text-[var(--color-ink)] group-hover:text-white transition">{{ $category->name }}</h3>
            </a>
        @endforeach
    </div>
</section>
@endif

{{-- Flash Sales --}}
@if($flashSales->isNotEmpty())
    <section class="max-w-full mx-auto px-4 py-12">
        <div class="flex items-center justify-between mb-6 border-b hairline pb-4">
            <h2 class="font-display text-2xl font-bold text-[var(--color-ink)]">
                <i class="fa-solid fa-bolt text-[var(--color-red)] mr-2"></i>Flash Sales
            </h2>
            <a href="{{ route('flash-sales.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium text-sm">View All →</a>
        </div>
        <div class="space-y-8">
            @foreach($flashSales as $flashSale)
                <div class="border hairline p-4 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 class="font-display text-xl font-bold text-[var(--color-ink)]">{{ $flashSale->name }}</h3>
                            <p class="text-sm text-[var(--color-steel)]">
                                {{ $flashSale->starts_at?->format('M d, Y H:i') }} — {{ $flashSale->ends_at?->format('M d, Y H:i') }}
                            </p>
                        </div>
                        <a href="{{ route('flash-sales.show', $flashSale) }}" class="text-sm font-medium text-[var(--color-red)] hover:text-[var(--color-red-dark)]">
                            View Deals →
                        </a>
                    </div>
                    @if($flashSale->products->isNotEmpty())
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                            @foreach($flashSale->products->take(4) as $product)
                                @include('components.store.product-card', [
                                    'product' => $product,
                                    'flashPrice' => $product->pivot->flash_price ?? $flashSale->sale_price,
                                    'flashOriginalPrice' => $product->base_price,
                                    'badgeText' => 'Flash Sale',
                                ])
                            @endforeach
                        </div>
                    @else
                        <a href="{{ route('flash-sales.show', $flashSale) }}" class="btn btn-primary">View Sale</a>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

{{-- Featured Products --}}
@if($featuredProducts->isNotEmpty())    
<section id="featured" class="max-w-full mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-6 border-b hairline pb-4">
        <h2 class="font-display text-2xl font-bold text-[var(--color-ink)]">Featured Products</h2>
        <a href="{{ route('shop', ['featured' => 1]) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium text-sm">View All →</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
        @foreach($featuredProducts as $product)
            @include('components.store.product-card', ['product' => $product])
        @endforeach
    </div>
</section>
@endif

{{-- Promotional Banners --}}
@if($promotionalBanners)
    @php $banner = $promotionalBanners; @endphp
        <section class="max-w-full mx-auto px-4 py-6">
            <div class="relative overflow-hidden group">
                @if($banner->image)
                    <img src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}" loading="lazy" class="w-full h-auto object-cover">
                @endif
                @if($banner->title || $banner->description || $banner->cta_text)
                    <div class="absolute inset-0 bg-black/40 flex items-center p-8 sm:p-12">
                        <div class="max-w-xl">
                            @if($banner->title)
                                <h3 class="font-display text-3xl font-bold text-white">{{ $banner->title }}</h3>
                            @endif
                            @if($banner->description)
                                <p class="text-lg text-white/85 mt-2">{{ $banner->description }}</p>
                            @endif
                            @if($banner->cta_text)
                                @if($banner->url)
                                    <a href="{{ $banner->url }}" class="inline-block mt-4 btn btn-primary">{{ $banner->cta_text }}</a>
                                @else
                                    <span class="inline-block mt-4 btn btn-primary">{{ $banner->cta_text }}</span>
                                @endif
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>
@endif

{{-- Sale Banners --}}
@if($saleBanners)
    @php $banner = $saleBanners; @endphp
    @php $bgColor = sanitize_color($banner->color, 'var(--color-red)'); @endphp
        <section class="max-w-full mx-auto px-4 py-6">
            <div class="p-8 sm:p-12 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6" style="background-color: {{ $bgColor }};">
                <div class="max-w-xl">
                    @if($banner->title)
                        <h3 class="font-display text-3xl font-bold text-white">{{ $banner->title }}</h3>
                    @endif
                    @if($banner->description)
                        <p class="text-lg text-white/85 mt-2">{{ $banner->description }}</p>
                    @endif
                </div>
                @if($banner->cta_text)
                    @if($banner->url)
                        <a href="{{ $banner->url }}" class="btn btn-dark whitespace-nowrap">{{ $banner->cta_text }}</a>
                    @else
                        <span class="btn btn-dark whitespace-nowrap">{{ $banner->cta_text }}</span>
                    @endif
                @endif
            </div>
        </section>
@endif

{{-- New Arrivals --}}
@if($newArrivals->isNotEmpty())
<section class="max-w-full mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-6 border-b hairline pb-4">
        <h2 class="font-display text-2xl font-bold text-[var(--color-ink)]">New Arrivals</h2>
        <a href="{{ route('shop', ['sort' => 'newest']) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium text-sm">View All →</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
        @foreach($newArrivals as $product)
            @include('components.store.product-card', ['product' => $product])
        @endforeach
    </div>
</section>
@else
<section class="max-w-full mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-6 border-b hairline pb-4">
        <h2 class="font-display text-2xl font-bold text-[var(--color-ink)]">New Arrivals</h2>
        <a href="{{ route('shop', ['sort' => 'newest']) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium text-sm">View All →</a>
    </div>
    <p class="text-sm text-[var(--color-steel)]">No new arrivals at the moment. Please check back later.</p>
</section>
@endif

{{-- Best Sellers --}}
@if($bestSellers->isNotEmpty())
<section class="max-w-full mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-6 border-b hairline pb-4">
        <h2 class="font-display text-2xl font-bold text-[var(--color-ink)]">Best Sellers</h2>
        <a href="{{ route('shop', ['sort' => 'best_selling']) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium text-sm">View All →</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
        @foreach($bestSellers as $product)
            @include('components.store.product-card', ['product' => $product])
        @endforeach
    </div>
</section>
@endif

{{-- Footer Banners --}}
@if($footerBanners)
    @php $banner = $footerBanners; @endphp
    @php $bgColor = sanitize_color($banner->color, 'var(--color-ink)'); @endphp
        <section class="relative w-full overflow-hidden min-h-[400px] lg:min-h-[500px] flex items-center" style="background-color: {{ $bgColor }};">
            @if($banner->image)
                <div class="absolute inset-0 flex justify-end">
                    <img src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}" loading="lazy" class="h-full w-full object-cover object-right lg:w-1/2 lg:object-contain lg:object-right" />
                </div>
            @endif
            <div class="relative max-w-full mx-auto px-6 sm:px-12 lg:px-16 py-12 w-full z-10">
                <div class="max-w-xl">
                    @if($banner->subtitle)
                        <p class="text-xs uppercase tracking-widest text-white/60 font-semibold mb-2">{{ $banner->subtitle }}</p>
                    @endif
                    @if($banner->title)
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-serif tracking-normal mb-4 text-white">{{ $banner->title }}</h2>
                    @endif
                    @if($banner->description)
                        <p class="text-sm sm:text-base text-white/70 font-light mb-8">{{ $banner->description }}</p>
                    @endif
                    @if($banner->cta_text)
                        @if($banner->url)
                            <a href="{{ $banner->url }}" class="px-6 py-2.5 text-xs uppercase tracking-wider border border-white text-white hover:bg-white hover:text-[var(--color-ink)] transition-colors duration-300 ease-in-out focus:outline-none">{{ $banner->cta_text }}</a>
                        @else
                            <span class="px-6 py-2.5 text-xs uppercase tracking-wider border border-white text-white hover:bg-white hover:text-[var(--color-ink)] transition-colors duration-300 ease-in-out cursor-default">{{ $banner->cta_text }}</span>
                        @endif
                    @endif
                </div>
            </div>
        </section>
@endif

{{-- Trust / Benefits --}}
<section class="bg-[var(--color-ink)] text-white py-12">
    <div class="max-w-full mx-auto px-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 border border-[var(--color-red)] flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-truck text-2xl text-[var(--color-red)]"></i>
                </div>
                <div>
                    <h4 class="font-semibold">Cash on Delivery</h4>
                    <p class="text-sm text-white/60">Pay when you receive</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 border border-[var(--color-red)] flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-shield-halved text-2xl text-[var(--color-red)]"></i>
                </div>
                <div>
                    <h4 class="font-semibold">Secure Payment</h4>
                    <p class="text-sm text-white/60">100% secure checkout</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 border border-[var(--color-red)] flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-rotate-left text-2xl text-[var(--color-red)]"></i>
                </div>
                <div>
                    <h4 class="font-semibold">Easy Returns</h4>
                    <p class="text-sm text-white/60">30-day return policy</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 border border-[var(--color-red)] flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-headset text-2xl text-[var(--color-red)]"></i>
                </div>
                <div>
                    <h4 class="font-semibold">24/7 Support</h4>
                    <p class="text-sm text-white/60">Dedicated support</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
