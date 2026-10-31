@extends('layouts.store')

@section('title', 'Search Results')

@section('content')
<div class="max-w-full mx-auto px-4 py-8">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)] transition">Home</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">Search Results</span>
    </nav>

    <h1 class="font-display text-3xl sm:text-4xl font-bold text-[var(--color-ink)] mb-2">
        @if(request('q'))
            Results for "{{ request('q') }}"
        @else
            All Products
        @endif
    </h1>
    <p class="text-[var(--color-steel)] mb-8">{{ $products->total() ?? count($products) }} products found</p>

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
                    $filterCategories = \Illuminate\Support\Facades\Cache::remember('categories.search', 1440, function () {
                        return \App\Models\Category::whereNull('parent_id')->where('is_active', true)->get();
                    });
                    $filterBrands = \Illuminate\Support\Facades\Cache::remember('brands.search', 1440, function () {
                        return \App\Models\Brand::where('is_active', true)->get();
                    });
                    $selectedCategories = (array) request('categories', []);
                    $selectedBrands = (array) request('brands', []);
                @endphp

                <div id="filters-panel" class="hidden lg:block space-y-6">
                    {{-- Category Filter --}}
                    @if($filterCategories->isNotEmpty())
                        <div class="bg-[var(--color-paper)] border hairline p-4">
                            <h3 class="font-display font-semibold text-[var(--color-ink)] mb-3">Categories</h3>
                            <div class="space-y-2">
                                @foreach($filterCategories as $category)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="categories[]" value="{{ $category->slug }}" {{ in_array($category->slug, $selectedCategories) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" data-action="applyFilters">
                                        <span class="text-sm text-[var(--color-ink-soft)]">{{ $category->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Brand Filter --}}
                    @if($filterBrands->isNotEmpty())
                        <div class="bg-[var(--color-paper)] border hairline p-4">
                            <h3 class="font-display font-semibold text-[var(--color-ink)] mb-3">Brands</h3>
                            <div class="space-y-2">
                                @foreach($filterBrands as $brand)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="brands[]" value="{{ $brand->slug }}" {{ in_array($brand->slug, $selectedBrands) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" data-action="applyFilters">
                                        <span class="text-sm text-[var(--color-ink-soft)]">{{ $brand->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </aside>

        {{-- Results --}}
        <div class="flex-1">
            @if(isset($products) && count($products))
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    @foreach($products as $product)
                        @include('components.store.product-card', ['product' => $product])
                    @endforeach
                </div>
                @if(method_exists($products, 'links'))
                    <div class="mt-8">{{ $products->withQueryString()->links() }}</div>
                @endif
            @else
                <div class="text-center py-16 border hairline bg-white rounded-lg">
                    <i class="fa-solid fa-magnifying-glass text-5xl text-[var(--color-line)] mx-auto mb-4"></i>
                    <h3 class="font-display text-lg font-medium text-[var(--color-ink)] mb-2">No results found</h3>
                    <p class="text-[var(--color-steel)] mb-4">Try different keywords or browse our categories.</p>
                    <a href="{{ route('shop') }}" class="bg-[var(--color-red)] text-white px-6 py-2 rounded font-medium hover:bg-[var(--color-red-dark)] transition">Browse All Products</a>
                </div>
            @endif
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
function applyFilters() {
    const params = new URLSearchParams(window.location.search);
    params.delete('categories[]');
    params.delete('brands[]');
    document.querySelectorAll('input[name="categories[]"]:checked').forEach(cb => params.append('categories[]', cb.value));
    document.querySelectorAll('input[name="brands[]"]:checked').forEach(cb => params.append('brands[]', cb.value));
    window.location.href = '{{ route("search") }}?' + params.toString();
}
</script>
@endsection
