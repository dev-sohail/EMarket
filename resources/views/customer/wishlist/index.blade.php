@extends('layouts.store')

@section('title', 'My Wishlist')

@section('content')
<div class="max-w-full mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)] transition">Home</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" class="hover:text-[var(--color-red)] transition">Dashboard</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">Wishlist</span>
    </nav>

    <h1 class="font-display text-3xl sm:text-4xl font-bold text-[var(--color-ink)] mb-6">My Wishlist</h1>

    @if(isset($items) && count($items))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
            @foreach($items as $item)
                @php $product = $item->product; @endphp
                @if($product)
                    <div class="bg-white rounded-lg border hairline group">
                        <div class="relative overflow-hidden bg-[var(--color-paper-dim)]">
                            <a href="{{ route('product.show', $product->slug) }}">
                                <div class="aspect-square flex items-center justify-center">
                                    @if($product->primary_image)
                                        <img src="{{ asset('storage/' . $product->primary_image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                                    @else
                                        <i class="fa-solid fa-image text-5xl text-[var(--color-line)]"></i>
                                    @endif
                                </div>
                            </a>
                            <button data-action="removeFromWishlist" data-args="[&quot;{{ $product->getKey() }}&quot;]" class="absolute top-2 right-2 w-8 h-8 bg-white rounded-full shadow flex items-center justify-center text-[var(--color-red)] hover:bg-[var(--color-paper-dim)] transition">
                                <i class="fa-solid fa-heart text-base"></i>
                            </button>
                        </div>
                        <div class="p-4">
                            <h3 class="font-medium text-[var(--color-ink)] line-clamp-2">
                                <a href="{{ route('product.show', $product->slug) }}" class="hover:text-[var(--color-red)] transition">{{ $product->name }}</a>
                            </h3>
                            <div class="flex items-center gap-2 mt-2">
                                @php
                                    $wishlistPrice = $product->final_price;
                                    $wishlistOriginalPrice = $product->base_price;
                                @endphp
                                <span class="price text-lg font-bold text-[var(--color-ink)]">{{ format_currency($wishlistPrice) }}</span>
                                @if($wishlistPrice < $product->base_price)
                                    <span class="price text-sm text-[var(--color-steel)] line-through">{{ format_currency($wishlistOriginalPrice) }}</span>
                                @endif
                            </div>
                            <button data-action="moveToCart" data-args="[&quot;{{ $product->getKey() }}&quot;]" class="mt-3 w-full bg-[var(--color-red)] text-white py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition">Move to Cart</button>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <div class="text-center py-16 bg-white rounded-lg border hairline">
            <i class="fa-solid fa-heart text-5xl text-[var(--color-line)] mx-auto mb-4"></i>
            <h3 class="font-display text-lg font-medium text-[var(--color-ink)] mb-2">Your wishlist is empty</h3>
            <p class="text-[var(--color-steel)] mb-4">Save items you love for later.</p>
            <a href="{{ route('shop') }}" class="bg-[var(--color-red)] text-white px-6 py-2 rounded font-medium hover:bg-[var(--color-red-dark)] transition">Browse Products</a>
        </div>
    @endif
</div>

<script nonce="{{ $cspNonce }}">
function removeFromWishlist(productId) {
    fetch('{{ url("ajax/wishlist/toggle") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ product_id: productId })
    }).then(r => r.json()).then(() => location.reload());
}
function moveToCart(productId) {
    fetch('{{ url("ajax/cart/add") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    }).then(r => {
        if (r.status === 419) {
            showToast('Session expired. Please refresh the page and try again.', 'error');
            return Promise.reject();
        }
        return r.json();
    }).then(data => {
        if (data && data.success) {
            removeFromWishlistByProduct(productId);
            updateCartCount();
            showToast('Moved to cart!');
        } else if (data) {
            showToast(data.message || 'Failed to move to cart', 'error');
        }
    }).catch(() => {});
}
function removeFromWishlistByProduct(productId) {
    fetch('{{ url("ajax/wishlist/toggle") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ product_id: productId })
    }).then(() => location.reload());
}
</script>
@endsection
