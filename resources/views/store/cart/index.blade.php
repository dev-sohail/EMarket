@extends('layouts.store')

@section('title', 'Shopping Cart')

@section('content')
{{-- Hero --}}
<section class="bg-[var(--color-ink)] text-white">
    <div class="max-w-full mx-auto px-4 py-12 sm:py-16">
        <nav class="flex items-center gap-2 text-sm text-white/55 mb-6">
            <a href="{{ route('home') }}" class="hover:text-white transition">Home</a>
            <span>/</span>
            <span class="text-white">Shopping Cart</span>
        </nav>
        <span class="eyebrow text-[var(--color-red)]">Cart</span>
        <h1 class="font-display text-3xl sm:text-4xl font-bold mt-2 leading-tight">Shopping Cart</h1>
    </div>
</section>

<div class="max-w-full mx-auto px-4 py-10">
    <p class="text-[var(--color-steel)] mb-6">{{ $cart->items->sum('quantity') ?? 0 }} {{ Str::plural('item', $cart->items->sum('quantity') ?? 0) }} in your cart</p>

    @if(session('success'))
        <div class="border hairline bg-[var(--color-paper-dim)] text-[var(--color-sale)] px-4 py-3 mb-4">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] px-4 py-3 mb-4">{{ session('error') }}</div>
    @endif

    @if($cart->items->isNotEmpty())
        <div class="space-y-4">
            @foreach($cart->items as $item)
                <div class="border hairline p-4 flex items-center gap-4">
                    <div class="w-20 h-20 bg-[var(--color-paper-dim)] flex-shrink-0 flex items-center justify-center overflow-hidden">
                        @php
                            $cartImage = $item->variant?->images->first()?->path ?? $item->product?->primary_image;
                        @endphp
                        @if($cartImage)
                                <img src="{{ asset('storage/' . $cartImage) }}" alt="{{ $item->product->name ?? 'Product' }}" class="w-full h-full object-cover" loading="lazy">
                        @else
                            <i class="fa-solid fa-image text-3xl text-[var(--color-line)]"></i>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-[var(--color-ink)] truncate">{{ $item->product->name ?? $item->product_name ?? 'Product' }}</h3>
                        @if($item->variant?->name)
                            <p class="text-sm text-[var(--color-steel)]">{{ $item->variant->name }}</p>
                        @endif
                        <p class="text-sm text-[var(--color-steel)] price">{{ format_currency($item->unit_price) }} each</p>
                        @if($item->product?->stock_status === \App\Enums\StockStatus::OutOfStock)
                            <span class="inline-block mt-1 text-xs text-[var(--color-red)] font-medium">Out of stock</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center border hairline">
                            <button type="button" data-action="updateQuantity" data-args="[&quot;{{ $item->getKey() }}&quot;, {{ $item->quantity - 1 }}]" class="px-3 py-1 text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] disabled:opacity-50" {{ $item->quantity <= 1 ? 'disabled' : '' }}>−</button>
                            <input type="number" value="{{ $item->quantity }}" min="1" max="99" data-action="updateQuantity" data-args="[&quot;{{ $item->getKey() }}&quot;, null]" data-arg-value="1" class="w-12 text-center border-x hairline py-1 focus:outline-none">
                            <button type="button" data-action="updateQuantity" data-args="[&quot;{{ $item->getKey() }}&quot;, {{ $item->quantity + 1 }}]" class="px-3 py-1 text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)]">+</button>
                        </div>
                        <div class="text-right min-w-[100px]">
                            <p class="font-semibold text-[var(--color-ink)] price">{{ format_currency($item->unit_price * $item->quantity) }}</p>
                            <button data-action="removeFromCart" data-args="[&quot;{{ $item->getKey() }}&quot;]" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm mt-1">Remove</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 border hairline p-6">
            <div class="flex justify-between text-lg font-bold mb-4 text-[var(--color-ink)]">
                <span>Subtotal</span>
                <span class="price">{{ format_currency($cart->subtotal) }}</span>
            </div>
            <p class="text-sm text-[var(--color-steel)] mb-4">Shipping & discounts are calculated at checkout.</p>
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="{{ route('shop') }}" class="btn btn-outline text-[var(--color-ink)] flex-1">
                    Continue Shopping
                </a>
                <a href="{{ route('checkout') }}" class="btn btn-primary flex-1">
                    Proceed to Checkout
                </a>
            </div>
        </div>
    @else
        <div class="text-center py-16 border hairline">
            <i class="fa-solid fa-cart-shopping text-5xl text-[var(--color-line)]"></i>
            <h3 class="font-display text-lg font-medium text-[var(--color-ink)] mb-2">Your cart is empty</h3>
            <p class="text-[var(--color-steel)] mb-4">Looks like you haven't added any products to your cart yet.</p>
            <a href="{{ route('shop') }}" class="btn btn-primary">
                Browse Products
            </a>
        </div>
    @endif
</div>

<script nonce="{{ $cspNonce }}">
function removeFromCart(itemId) {
    if (!confirm('Are you sure you want to remove this item from your cart?')) return;
    fetch('{{ url("ajax/cart/remove") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ cart_item_id: itemId })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to remove item.');
        }
    }).catch(() => alert('Failed to remove item.'));
}

function updateQuantity(itemId, quantity) {
    quantity = parseInt(quantity);
    if (isNaN(quantity) || quantity < 1) {
        alert('Quantity must be at least 1.');
        return;
    }
    if (quantity > 99) {
        alert('Maximum quantity is 99.');
        return;
    }
    fetch('{{ url("ajax/cart/update") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ cart_item_id: itemId, quantity: quantity })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to update quantity.');
        }
    }).catch(() => alert('Failed to update quantity.'));
}
</script>
@endsection
