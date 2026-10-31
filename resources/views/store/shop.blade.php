@extends('layouts.store')

@section('title', 'Shop')

@section('content')
{{-- Shop Hero --}}
<section class="bg-[var(--color-ink)] text-white">
    <div class="max-w-full mx-auto px-4 py-12 sm:py-16">
        <nav class="flex items-center gap-2 text-sm text-white/55 mb-6">
            <a href="{{ route('home') }}" class="hover:text-white transition">Home</a>
            <span>/</span>
            <span class="text-white">Shop</span>
        </nav>
        <span class="eyebrow text-[var(--color-red)]">Shop</span>
        <h1 class="font-display text-3xl sm:text-4xl font-bold mt-2 leading-tight">All Products</h1>
    </div>
</section>

<div class="max-w-full mx-auto px-4 py-10">
    <div class="flex flex-col lg:flex-row gap-8">
        {{-- Filters Sidebar --}}
        <aside class="w-full lg:w-64 flex-shrink-0">
            <div class="lg:sticky lg:top-4">
                {{-- Mobile Filter Toggle --}}
                <button data-toggle="filters-panel" class="lg:hidden w-full border hairline bg-[var(--color-paper)] px-4 py-3 flex items-center justify-between mb-4">
                    <span class="font-display font-semibold text-[var(--color-ink)]">Filters</span>
                    <i class="fa-solid fa-filter text-xl text-[var(--color-ink)]"></i>
                </button>

                @php
                    $activeCategories = \Illuminate\Support\Facades\Cache::remember('categories.shop', 1440, function () {
                        return \App\Models\Category::whereNull('parent_id')->where('is_active', true)->get();
                    });
                    $activeBrands = \Illuminate\Support\Facades\Cache::remember('brands.shop', 1440, function () {
                        return \App\Models\Brand::where('is_active', true)->get();
                    });
                    $selectedCategories = (array) request('categories', []);
                    $selectedBrands = (array) request('brands', []);
                @endphp

                <div id="filters-panel" class="hidden lg:block space-y-6">
                    {{-- Category Filter --}}
                    @if($activeCategories->isNotEmpty())
                        <div class="bg-[var(--color-paper)] border hairline p-4">
                            <h3 class="font-display font-semibold text-[var(--color-ink)] mb-3">Categories</h3>
                            <div class="space-y-2">
                                @foreach($activeCategories as $category)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="categories[]" value="{{ $category->slug }}" {{ in_array($category->slug, $selectedCategories) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" data-action="applyFilters">
                                        <span class="text-sm text-[var(--color-ink-soft)]">{{ $category->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Price Filter --}}
                    <div class="bg-[var(--color-paper)] border hairline p-4">
                        <h3 class="font-display font-semibold text-[var(--color-ink)] mb-3">Price Range</h3>
                        <div class="flex items-center gap-2">
                            <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="w-full border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                            <span class="text-[var(--color-steel)]">-</span>
                            <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" class="w-full border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                        </div>
                        <button data-action="applyFilters" class="btn btn-primary mt-3 w-full">Apply</button>
                    </div>

                    {{-- Brand Filter --}}
                    @if($activeBrands->isNotEmpty())
                        <div class="bg-[var(--color-paper)] border hairline p-4">
                            <h3 class="font-display font-semibold text-[var(--color-ink)] mb-3">Brands</h3>
                            <div class="space-y-2">
                                @foreach($activeBrands as $brand)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="brands[]" value="{{ $brand->slug }}" {{ in_array($brand->slug, $selectedBrands) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" data-action="applyFilters">
                                        <span class="text-sm text-[var(--color-ink-soft)]">{{ $brand->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Attribute Filters --}}
                    @foreach($filterableAttributes as $attribute)
                        @if($attribute->values->isNotEmpty())
                            @php $selectedValues = (array) ($filters['attribute_value_ids'][$attribute->slug] ?? []); @endphp
                            <div class="bg-[var(--color-paper)] border hairline p-4">
                                <h3 class="font-display font-semibold text-[var(--color-ink)] mb-3">{{ $attribute->name }}</h3>
                                <div class="space-y-2 max-h-56 overflow-y-auto">
                                    @foreach($attribute->values as $value)
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" name="attributes[{{ $attribute->slug }}][]" value="{{ $value->getKey() }}" {{ in_array($value->getKey(), $selectedValues) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" data-action="applyFilters">
                                            @if($attribute->type === \App\Enums\AttributeType::Color->value)
                                                <span class="w-3 h-3 rounded-full border hairline shrink-0" style="background-color: {{ $value->value }}"></span>
                                            @endif
                                            <span class="text-sm text-[var(--color-ink-soft)]">{{ $value->value }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    {{-- Sale Filter --}}
                    <div class="bg-[var(--color-paper)] border hairline p-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="sale" value="1" {{ request('sale') ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" data-action="applyFilters">
                            <span class="text-sm font-medium text-[var(--color-ink)]">On Sale</span>
                        </label>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Products --}}
        <div class="flex-1">
            {{-- Sort & Results --}}
            <div class="flex items-center justify-between mb-6 border-b hairline pb-4">
                <p class="text-sm text-[var(--color-steel)]">{{ $products->total() ?? 0 }} products found</p>
                <select data-goto class="border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                    <option value="{{ route('shop', array_merge(request()->query(), ['sort' => 'default'])) }}" {{ request('sort', 'default') == 'default' ? 'selected' : '' }}>Default Sort</option>
                    <option value="{{ route('shop', array_merge(request()->query(), ['sort' => 'best_selling'])) }}" {{ request('sort') == 'best_selling' ? 'selected' : '' }}>Best Selling</option>
                    <option value="{{ route('shop', array_merge(request()->query(), ['sort' => 'newest'])) }}" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest</option>
                    <option value="{{ route('shop', array_merge(request()->query(), ['sort' => 'price_asc'])) }}" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                    <option value="{{ route('shop', array_merge(request()->query(), ['sort' => 'price_desc'])) }}" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    <option value="{{ route('shop', array_merge(request()->query(), ['sort' => 'name_asc'])) }}" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>Name: A-Z</option>
                    <option value="{{ route('shop', array_merge(request()->query(), ['sort' => 'name_desc'])) }}" {{ request('sort') == 'name_desc' ? 'selected' : '' }}>Name: Z-A</option>
                </select>
            </div>

            {{-- Active Filters --}}
            @if(request()->hasAny(['categories', 'brands', 'min_price', 'max_price', 'q', 'sale', 'attributes']))
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <span class="text-sm text-[var(--color-steel)]">Active filters:</span>
                    @foreach($selectedCategories as $cat)
                        <span class="inline-flex items-center gap-1 border hairline bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm px-3 py-1">
                            {{ $cat }}
                            <button data-action="removeFilter" data-args="[&quot;categories&quot;, &quot;{{ $cat }}&quot;]" class="hover:text-[var(--color-red)] transition">×</button>
                        </span>
                    @endforeach
                    @foreach($selectedBrands as $brand)
                        <span class="inline-flex items-center gap-1 border hairline bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm px-3 py-1">
                            {{ $brand }}
                            <button data-action="removeFilter" data-args="[&quot;brands&quot;, &quot;{{ $brand }}&quot;]" class="hover:text-[var(--color-red)] transition">×</button>
                        </span>
                    @endforeach
                    @if(request('q'))
                        <span class="inline-flex items-center gap-1 border hairline bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm px-3 py-1">
                            "{{ request('q') }}"
                            <a href="{{ route('shop', request()->except('q')) }}" class="hover:text-[var(--color-red)] transition">×</a>
                        </span>
                    @endif
                    @if(request('min_price'))
                        <span class="inline-flex items-center gap-1 border hairline bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm px-3 py-1">
                            Min: {{ request('min_price') }}
                            <a href="{{ route('shop', request()->except('min_price')) }}" class="hover:text-[var(--color-red)] transition">×</a>
                        </span>
                    @endif
                    @if(request('max_price'))
                        <span class="inline-flex items-center gap-1 border hairline bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm px-3 py-1">
                            Max: {{ request('max_price') }}
                            <a href="{{ route('shop', request()->except('max_price')) }}" class="hover:text-[var(--color-red)] transition">×</a>
                        </span>
                    @endif
                    @foreach($filterableAttributes as $attribute)
                        @foreach((array) ($filters['attribute_value_ids'][$attribute->slug] ?? []) as $valueId)
                            @php $valueLabel = $attribute->values->firstWhere('_id', $valueId)?->value ?? $valueId; @endphp
                            <span class="inline-flex items-center gap-1 border hairline bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm px-3 py-1">
                                {{ $attribute->name }}: {{ $valueLabel }}
                                <button data-action="removeFilter" data-args="[&quot;attributes&quot;, &quot;{{ $attribute->slug }}&quot;, &quot;{{ $valueId }}&quot;]" class="hover:text-[var(--color-red)] transition">×</button>
                            </span>
                        @endforeach
                    @endforeach
                    @if(request('sale'))
                        <span class="inline-flex items-center gap-1 border hairline bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm px-3 py-1">
                            On Sale
                            <a href="{{ route('shop', request()->except('sale')) }}" class="hover:text-[var(--color-red)] transition">×</a>
                        </span>
                    @endif
                    <a href="{{ route('shop') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Clear all</a>
                </div>
            @endif

            {{-- Product Grid --}}
            @if($products->count())
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

                {{-- Pagination --}}
                <div class="mt-8">
                    {{ $products->withQueryString()->links() }}
                </div>
            @else
                <div class="text-center py-16 border hairline">
                    <i class="fa-solid fa-magnifying-glass text-5xl text-[var(--color-line)] mx-auto mb-4"></i>
                    <h3 class="font-display text-lg font-medium text-[var(--color-ink)] mb-2">No products found</h3>
                    <p class="text-[var(--color-steel)] mb-4">Try adjusting your filters or search terms.</p>
                    <a href="{{ route('shop') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear all filters</a>
                </div>
            @endif
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
function applyFilters() {
    const params = new URLSearchParams();
    document.querySelectorAll('input[name="categories[]"]:checked').forEach(cb => params.append('categories[]', cb.value));
    document.querySelectorAll('input[name="brands[]"]:checked').forEach(cb => params.append('brands[]', cb.value));
    document.querySelectorAll('input[name^="attributes["]:checked').forEach(cb => params.append(cb.name, cb.value));
    const minPrice = document.querySelector('input[name="min_price"]').value;
    const maxPrice = document.querySelector('input[name="max_price"]').value;
    if (minPrice) params.set('min_price', minPrice);
    if (maxPrice) params.set('max_price', maxPrice);
    const saleCheckbox = document.querySelector('input[name="sale"]');
    if (saleCheckbox && saleCheckbox.checked) params.set('sale', '1');
    window.location.href = '{{ route("shop") }}?' + params.toString();
}
function removeFilter(type, value, valueId) {
    const params = new URLSearchParams(window.location.search);
    if (type === 'sale') {
        params.delete('sale');
    } else if (type === 'attributes') {
        // Attribute inputs are named `attributes[<slug>][]`, so the key carries
        // the trailing `[]`. Read the current values before deleting, then keep
        // only the untouched ones; the old code deleted first and read nothing.
        const key = 'attributes[' + value + '][]';
        const remaining = params.getAll(key).filter(v => v !== valueId);
        params.delete(key);
        remaining.forEach(v => params.append(key, v));
    } else {
        // Same delete-before-read bug here: deleting the key first meant no
        // value was ever re-added, so removing one chip dropped every filter of
        // that type.
        const key = type + '[]';
        const remaining = params.getAll(key).filter(v => v !== value);
        params.delete(key);
        remaining.forEach(v => params.append(key, v));
    }
    window.location.href = '{{ route("shop") }}?' + params.toString();
}
</script>
@endsection