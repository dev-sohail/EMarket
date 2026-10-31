@extends('layouts.store')

@section('title', $category->name)

@section('content')
{{-- Category Hero --}}
<section class="bg-[var(--color-ink)] text-white">
    <div class="max-w-full mx-auto px-4 py-12 sm:py-16">
        {{-- Breadcrumb --}}
        <nav class="flex items-center gap-2 text-sm text-white/55 mb-6">
            <a href="{{ route('home') }}" class="hover:text-white transition">Home</a>
            <span>/</span>
            @if($category->parent)
                <a href="{{ route('category.show', $category->parent->slug) }}" class="hover:text-white transition">{{ $category->parent->name }}</a>
                <span>/</span>
            @endif
            <span class="text-white">{{ $category->name }}</span>
        </nav>

        <span class="eyebrow text-[var(--color-red)]">Category</span>
        <h1 class="font-display text-3xl sm:text-4xl font-bold mt-2 leading-tight">{{ $category->name }}</h1>
        @if($category->description)
            {{-- Sanitised on render as well as on submit: rows written before
                 the SafeHtml rule existed still have to be neutralised. The
                 regex block-list this replaced required a quoted attribute
                 value, so `<img src=x onerror=alert(1)>` passed through it. --}}
            <p class="text-white/70 mt-3 max-w-2xl">{!! \App\Rules\SafeHtml::sanitize($category->description, \App\Rules\SafeHtml::PROFILE_CONTENT) !!}</p>
        @endif
    </div>
</section>

<div class="max-w-full mx-auto px-4 py-10">
    {{-- Subcategories --}}
    @if($subcategories->isNotEmpty())
        <section class="mb-10">
            <h2 class="font-display text-xl font-bold text-[var(--color-ink)] mb-4">Subcategories</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-px bg-[var(--color-line)] border hairline">
                @foreach($subcategories as $sub)
                    <a href="{{ route('category.show', $sub->slug) }}" class="group bg-[var(--color-paper)] hover:bg-[var(--color-ink)] transition p-6 text-center">
                        <div class="w-14 h-14 mx-auto flex items-center justify-center mb-3">
                            <i class="fa-solid fa-box text-2xl text-[var(--color-red)] group-hover:text-white transition"></i>
                        </div>
                        <h3 class="font-medium text-sm text-[var(--color-ink)] group-hover:text-white transition">{{ $sub->name }}</h3>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="flex flex-col lg:flex-row gap-8">
        {{-- Filters Sidebar --}}
        <aside class="w-full lg:w-64 flex-shrink-0">
            <div class="lg:sticky lg:top-4 space-y-6">
                {{-- Price Filter --}}
                <div class="bg-[var(--color-paper)] border hairline p-4">
                    <h3 class="font-display font-semibold text-[var(--color-ink)] mb-3">Price Range</h3>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="w-full border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                        <span class="text-[var(--color-steel)]">-</span>
                        <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" class="w-full border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                    </div>
                </div>

                {{-- Brand Filter --}}
                @php
                    $brands = \Illuminate\Support\Facades\Cache::remember('category.brands.' . $category->getKey(), 1440, function () use ($category) {
                        // The filter used to name the column `categories.id`, but no table
                        // in this schema has an `id` column: every primary key is the
                        // string `_id`. MySQL rejected it, so the whole category page 500'd
                        // here, while SQLite quietly treated it as a string literal and
                        // the suite stayed green.
                        return \App\Models\Brand::whereHas('products', function ($q) use ($category) {
                            $q->whereHas('categories', fn($cq) => $cq->where('categories._id', $category->getKey()));
                        })->get();
                    });
                @endphp
                @if($brands->isNotEmpty())
                    <div class="bg-[var(--color-paper)] border hairline p-4">
                        <h3 class="font-display font-semibold text-[var(--color-ink)] mb-3">Brands</h3>
                        <div class="space-y-2">
                            @foreach($brands as $brand)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="brands[]" value="{{ $brand->slug }}" class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                    <span class="text-sm text-[var(--color-ink-soft)]">{{ $brand->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </aside>

        {{-- Products --}}
        <div class="flex-1">
            <div class="flex items-center justify-between mb-6 border-b hairline pb-4">
                <p class="text-sm text-[var(--color-steel)]">{{ $products->total() ?? count($products) }} products</p>
                <form method="GET" action="{{ route('category.show', $category->slug) }}" class="flex items-center gap-2">
                    <select name="sort" data-auto-submit class="border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                        <option value="default" {{ request('sort') === 'default' ? 'selected' : '' }}>Default Sort</option>
                        <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Newest</option>
                        <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    </select>
                </form>
            </div>

            @if(isset($products) && count($products))
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    @foreach($products as $product)
                        @php
                            $activeFlashSale = $product->flashSales
                                ->where('is_active', true)
                                ->where('starts_at', '<=', now())
                                ->where('ends_at', '>=', now())
                                ->first();

                            $activeCampaign = $product->campaigns
                                ->where('is_active', true)
                                ->where('starts_at', '<=', now())
                                ->where(function ($q) {
                                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                                })
                                ->first();

                            $promoPrice = null;
                            $promoOriginalPrice = $product->base_price;
                            $promoBadge = null;

                            if ($activeFlashSale) {
                                $promoPrice = $product->pivot->flash_price ?? $activeFlashSale->sale_price;
                                $promoBadge = 'Flash Sale';
                            } elseif ($activeCampaign) {
                                $promoPrice = $product->pivot->campaign_price ?? null;
                                $promoBadge = $activeCampaign->name;
                            }
                        @endphp
                        @include('components.store.product-card', [
                            'product' => $product,
                            'flashPrice' => $promoPrice,
                            'flashOriginalPrice' => $promoOriginalPrice,
                            'badgeText' => $promoBadge,
                        ])
                    @endforeach
                </div>
                @if(method_exists($products, 'links'))
                    <div class="mt-8">{{ $products->withQueryString()->links() }}</div>
                @endif
            @else
                <div class="text-center py-16 border hairline">
                    <i class="fa-solid fa-box text-5xl text-[var(--color-line)] mx-auto mb-4"></i>
                    <h3 class="font-display text-lg font-medium text-[var(--color-ink)] mb-2">No products in this category</h3>
                    <a href="{{ route('shop') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Browse All Products</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
