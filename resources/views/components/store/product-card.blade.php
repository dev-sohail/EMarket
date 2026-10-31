@props([
    'product',
    'flashPrice' => null,
    'flashOriginalPrice' => null,
    'badgeText' => 'Flash Sale',
])

<div class="bg-[var(--color-paper)] border hairline hover:border-[var(--color-ink)] transition group">
    <div class="relative overflow-hidden bg-[var(--color-paper-dim)]">
        <a href="{{ route('product.show', $product->slug) }}">
            <div class="aspect-square flex items-center justify-center">
                @if($product->primary_image)
                    <img src="{{ asset('storage/' . $product->primary_image) }}" alt="{{ $product->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                @else
                    <i class="fa-solid fa-image text-5xl text-[var(--color-line)]"></i>
                @endif
            </div>
        </a>
        @if($flashPrice)
            <span class="absolute top-2 left-2 bg-[var(--color-red)] text-white text-xs font-bold px-2 py-1 font-display">
                {{ $badgeText }}
            </span>
        @elseif($product->sale_price || in_array($product->discount_type ?? 'none', ['percent', 'fixed']))
            <span class="absolute top-2 left-2 bg-[var(--color-red)] text-white text-xs font-bold px-2 py-1 font-display">
                @if($product->discount_type === 'percent')
                    -{{ $product->discount_value }}%
                @elseif($product->discount_type === 'fixed')
                    -{{ format_currency($product->discount_value) }}
                @else
                    -{{ round((1 - $product->sale_price / $product->base_price) * 100) }}%
                @endif
            </span>
        @endif
        <button type="button" data-action="add-to-wishlist" data-product-id="{{ $product->getKey() }}" data-url="{{ url('ajax/wishlist/toggle') }}" data-login-url="{{ route('login') }}" class="absolute top-2 right-2 w-8 h-8 bg-[var(--color-paper)] shadow flex items-center justify-center hover:text-[var(--color-red)] transition opacity-0 group-hover:opacity-100" aria-label="Add {{ $product->name }} to wishlist">
            <i class="fa-solid fa-heart text-base text-[var(--color-steel)]"></i>
        </button>
        @if($product->variants && count($product->variants))
            <a href="{{ route('product.show', $product->slug) }}" class="absolute bottom-2 right-2 w-8 h-8 bg-[var(--color-ink)] text-white shadow flex items-center justify-center hover:bg-[var(--color-red)] transition opacity-0 group-hover:opacity-100" title="Select options" aria-label="View options for {{ $product->name }}">
                <i class="fa-solid fa-plus text-base"></i>
            </a>
        @else
            <button type="button" data-action="add-to-cart" data-product-id="{{ $product->getKey() }}" data-url="{{ url('ajax/cart/add') }}" class="absolute bottom-2 right-2 w-8 h-8 bg-[var(--color-ink)] text-white shadow flex items-center justify-center hover:bg-[var(--color-red)] transition opacity-0 group-hover:opacity-100" aria-label="Add {{ $product->name }} to cart">
                <i class="fa-solid fa-plus text-base"></i>
            </button>
        @endif
    </div>
    <div class="p-4">
        @if($product->categories && $product->categories->count())
            @php $firstCategory = $product->categories->first(); @endphp
            @if($firstCategory)
                <a href="{{ route('category.show', $firstCategory->slug) }}" class="text-xs text-[var(--color-red)] hover:underline uppercase tracking-wide font-medium">{{ $firstCategory->name }}</a>
            @endif
        @endif
        <h3 class="mt-1 font-medium text-[var(--color-ink)] line-clamp-2">
            <a href="{{ route('product.show', $product->slug) }}" class="hover:text-[var(--color-red)] transition">{{ $product->name }}</a>
        </h3>
        {{-- Rating --}}
        @if(setting('reviews_enabled', true))
            <div class="flex items-center gap-1 mt-2">
                @for($i = 1; $i <= 5; $i++)
                    @if($i <= ($product->average_rating ?? 0))
                        <i class="fa-solid fa-star text-xs text-[var(--color-red)]"></i>
                    @else
                        <i class="fa-regular fa-star text-xs text-[var(--color-line)]"></i>
                    @endif
                @endfor
                <span class="text-xs text-[var(--color-steel)] ml-1">({{ $product->reviews_count ?? 0 }})</span>
            </div>
        @endif
        <div class="flex items-center gap-2 mt-2">
            @if($flashPrice)
                <span class="price text-lg font-bold text-[var(--color-red)]">{{ format_currency($flashPrice) }}</span>
                <span class="price text-sm text-[var(--color-steel)] line-through">{{ format_currency($flashOriginalPrice ?? $product->base_price) }}</span>
            @else
                <span class="price text-lg font-bold text-[var(--color-ink)]">{{ format_currency($product->final_price) }}</span>
                @if($product->sale_price || in_array($product->discount_type ?? 'none', ['percent', 'fixed']))
                    <span class="price text-sm text-[var(--color-steel)] line-through">{{ format_currency($product->sale_price ?? $product->base_price) }}</span>
                @endif
            @endif
        </div>
    </div>
</div>
