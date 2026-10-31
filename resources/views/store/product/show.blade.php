@extends('layouts.store')

@section('title', $product->name)

@section('meta_description', Str::limit(strip_tags($product->short_description ?? $product->description), 160))

@section('content')
@php
    $activeFlashSale = $activeFlashSale ?? null;
    $activeCampaign = $activeCampaign ?? null;
@endphp
<div class="max-w-full mx-auto px-4 py-8">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)] transition">Home</a>
        <span>/</span>
        <a href="{{ route('shop') }}" class="hover:text-[var(--color-red)] transition">Shop</a>
        <span>/</span>
        @if($product->categories->count())
            @php $firstCategory = $product->categories->first(); @endphp
            @if($firstCategory)
                <a href="{{ route('category.show', $firstCategory->slug) }}" class="hover:text-[var(--color-red)] transition">{{ $firstCategory->name }}</a>
                <span>/</span>
            @endif
        @endif
        <span class="text-[var(--color-ink)]">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
        {{-- Product Images --}}
        <div id="product-gallery"
             x-data="{ activeImage: 0, images: {{ Js::from($product->images->map(fn ($image) => asset('storage/' . $image->path))->values()) }} }">
            <div class="bg-[var(--color-paper-dim)] border hairline overflow-hidden mb-4 mx-auto w-full sm:max-w-[500px] lg:max-w-[700px]">
                @if(count($product->images))
                    <img :src="images[activeImage]" src="{{ asset('storage/' . $product->images->first()->path) }}" alt="{{ $product->name }}" class="w-full h-auto">
                @else
                    <div class="w-full h-full flex items-center justify-center">
                        <i class="fa-solid fa-image text-6xl text-[var(--color-line)]"></i>
                    </div>
                @endif
            </div>
            @if(count($product->images) > 1)
                <div class="grid grid-cols-5 gap-2">
                    @foreach($product->images as $index => $image)
                        <button @click="activeImage = {{ $index }}" :class="activeImage === {{ $index }} ? 'border-[var(--color-ink)]' : 'border-[var(--color-line)]'" class="aspect-square bg-[var(--color-paper-dim)] border-2 overflow-hidden">
                            <img src="{{ asset('storage/' . $image->path) }}" alt="{{ $product->name }} - {{ $index + 1 }}" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Product Info --}}
        <div>
            @if($product->categories->count())
                @php $firstCategory = $product->categories->first(); @endphp
                @if($firstCategory)
                    <a href="{{ route('category.show', $firstCategory->slug) }}" class="text-[var(--color-red)] text-sm hover:underline uppercase tracking-wide font-medium">{{ $firstCategory->name }}</a>
                @endif
            @endif
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-[var(--color-ink)] mt-1 leading-tight">{{ $product->name }}</h1>

            {{-- Stock Status --}}
            <div class="mt-3">
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium {{ $product->stock_status === \App\Enums\StockStatus::InStock ? 'bg-green-100 text-green-800' : ($product->stock_status === \App\Enums\StockStatus::OutOfStock ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                    {{ $product->stock_status->label() }}
                </span>
            </div>

            {{-- Rating --}}
            @if(setting('reviews_enabled', true))
                <div class="flex items-center gap-2 mt-3">
                    <div class="flex">
                        @for($i = 1; $i <= 5; $i++)
                            @if($i <= ($product->average_rating ?? 0))
                                <i class="fa-solid fa-star text-lg text-[var(--color-red)]"></i>
                            @else
                                <i class="fa-regular fa-star text-lg text-[var(--color-line)]"></i>
                            @endif
                        @endfor
                    </div>
                    <span class="text-sm text-[var(--color-steel)]">({{ $product->reviews_count ?? 0 }} reviews)</span>
                </div>
            @endif

            {{-- Price --}}
            @php
                $displayPrice = $product->final_price;
                $originalPrice = $product->base_price;
                $promotionLabel = null;

                if ($activeFlashSale) {
                    // $flashPrice is this product's pivot price, resolved in the
                    // controller; the sale-wide price is the fallback.
                    $effectiveFlashPrice = $flashPrice ?? $activeFlashSale->sale_price;
                    if ($effectiveFlashPrice !== null && $effectiveFlashPrice !== '') {
                        $displayPrice = (float) $effectiveFlashPrice;
                        $originalPrice = $product->base_price;
                        $promotionLabel = 'Flash Sale';
                    }
                } elseif ($activeCampaign) {
                    if ($campaignPrice !== null && $campaignPrice !== '') {
                        $displayPrice = (float) $campaignPrice;
                        $originalPrice = $product->base_price;
                        $promotionLabel = $activeCampaign->name;
                    }
                }
            @endphp
            <div class="flex items-baseline gap-3 mt-4">
                <span class="price text-3xl font-bold text-[var(--color-ink)]" id="product-price">{{ format_currency($displayPrice) }}</span>
                @if($displayPrice < $product->base_price)
                    <span class="price text-xl text-[var(--color-steel)] line-through">{{ format_currency($originalPrice) }}</span>
                    <span class="bg-[var(--color-red)] text-white text-sm font-medium px-2 py-1">Save {{ format_currency($originalPrice - $displayPrice) }}</span>
                @elseif($product->sale_price && $product->sale_price < $product->base_price)
                    <span class="price text-xl text-[var(--color-steel)] line-through">{{ format_currency($product->base_price) }}</span>
                    <span class="bg-[var(--color-red)] text-white text-sm font-medium px-2 py-1">Save {{ format_currency($product->base_price - $product->sale_price) }}</span>
                @elseif($product->discount_type !== 'none' && $product->discount_value > 0)
                    <span class="bg-[var(--color-red)] text-white text-sm font-medium px-2 py-1">Save {{ format_currency($product->base_price - $product->final_price) }}</span>
                @endif
                @if($promotionLabel)
                    <span class="bg-[var(--color-ink)] text-white text-xs font-medium px-2 py-1 uppercase tracking-wide">{{ $promotionLabel }}</span>
                @endif
            </div>

            {{-- Short Description --}}
            @if($product->short_description)
                <p class="text-[var(--color-ink-soft)] mt-4">{{ $product->short_description }}</p>
            @endif

            <hr class="my-6 border-0 border-t hairline">

            {{-- Variants --}}
            @php
                $variantAttributes = $product->attributeValues
                    ->load('attribute')
                    ->groupBy('attribute.id')
                    ->map(fn($values, $attrId) => [
                        'attribute' => $values->first()->attribute,
                        'values' => $values->unique('id')->values(),
                    ])
                    ->values();
                $hasVariantAttributes = $variantAttributes->isNotEmpty();
            @endphp
            @if($hasVariantAttributes)
                <div class="space-y-4">
                    <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Select Options</label>
                    @foreach($variantAttributes as $attrGroup)
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">{{ $attrGroup['attribute']->name }}</label>
                            <div class="flex flex-wrap gap-2" data-attribute-id="{{ $attrGroup['attribute']->getKey() }}">
                                @foreach($attrGroup['values'] as $attrValue)
                                    <button type="button"
                                        data-attribute-value-id="{{ $attrValue->getKey() }}"
                                        data-action="selectAttributeValue"
                                        class="attribute-value-btn border hairline px-4 py-2 text-sm transition border-[var(--color-line)] hover:border-[var(--color-ink)]">
                                        {{ $attrValue->value }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <p id="variant-error" class="hidden text-sm text-[var(--color-red)]">Please select all options before adding to cart.</p>
                </div>
            @elseif($product->variants && count($product->variants))
                <div class="space-y-4">
                    <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Select Variant</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($product->variants as $variant)
                            <button type="button"
                                data-variant-id="{{ $variant->getKey() }}"
                                data-variant-price="{{ $variant->final_price }}"
                                data-variant-compare-price="{{ $variant->list_price > $variant->final_price ? $variant->list_price : 0 }}"
                                data-variant-stock="{{ $variant->stock_quantity }}"
                                data-variant-image="{{ $variant->image }}"
                                data-action="selectVariant" data-args="[&quot;{{ $variant->getKey() }}&quot;]"
                                class="variant-btn border hairline px-4 py-2 text-sm transition border-[var(--color-line)] hover:border-[var(--color-ink)] {{ $variant->stock_quantity <= 0 ? 'opacity-50 cursor-not-allowed' : '' }}"
                                {{ $variant->stock_quantity <= 0 ? 'disabled' : '' }}>
                                {{ $variant->name }} — {{ format_currency($variant->final_price) }}
                                @if($variant->stock_quantity <= 0)
                                    <span class="text-[var(--color-red)] ml-1">(Out of stock)</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                    <p id="variant-error" class="hidden text-sm text-[var(--color-red)]">Please select a variant before adding to cart.</p>
                </div>
            @endif

            {{-- Quantity --}}
            <div class="mt-6">
                <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Quantity</label>
                <div class="flex items-center gap-3">
                    <div class="flex items-center border hairline">
                        <button type="button" data-action="updateQuantity" data-args="[-1]" class="px-3 py-2 hover:bg-[var(--color-paper-dim)] transition">-</button>
                        <input type="number" id="quantity" value="1" min="1" max="99" class="w-16 text-center border-x hairline focus:outline-none">
                        <button type="button" data-action="updateQuantity" data-args="[1]" class="px-3 py-2 hover:bg-[var(--color-paper-dim)] transition">+</button>
                    </div>
                    <span class="text-sm text-[var(--color-steel)]">{{ $product->stock_status === \App\Enums\StockStatus::InStock ? 'In Stock' : 'Out of Stock' }}</span>
                </div>
            </div>

            {{-- Add to Cart & Buy Now --}}
            <div class="flex gap-3 mt-6">
                <button data-action="addToCartFromProduct" data-args="[&quot;{{ $product->getKey() }}&quot;]" class="btn btn-primary flex-1">
                    <i class="fa-solid fa-cart-shopping text-xl"></i>
                    Add to Cart
                </button>
                <button data-action="buyNow" data-args="[&quot;{{ $product->getKey() }}&quot;]" class="btn btn-dark flex-1">
                    Buy Now
                </button>
            </div>

            {{-- Wishlist --}}
            <button data-action="addToWishlist" data-args="[&quot;{{ $product->getKey() }}&quot;]" class="btn btn-outline text-[var(--color-ink)] w-full mt-4">
                <i class="fa-solid fa-heart text-xl"></i>
                Add to Wishlist
            </button>
        </div>
    </div>

    <div class="mt-12" x-data="{ activeTab: 'description' }">
        <div class="border-b hairline">
            <nav class="flex gap-8">
                <button @click="activeTab = 'description'" :class="activeTab === 'description' ? 'border-[var(--color-ink)] text-[var(--color-ink)]' : 'border-transparent text-[var(--color-steel)] hover:text-[var(--color-ink)]'" class="py-3 border-b-2 font-medium text-sm transition">Description</button>
                @if(setting('reviews_enabled', true))
                    <button @click="activeTab = 'reviews'" :class="activeTab === 'reviews' ? 'border-[var(--color-ink)] text-[var(--color-ink)]' : 'border-transparent text-[var(--color-steel)] hover:text-[var(--color-ink)]'" class="py-3 border-b-2 font-medium text-sm transition">Reviews ({{ $product->reviews_count ?? 0 }})</button>
                @endif
            </nav>
        </div>

        <div class="py-6">
            <div x-show="activeTab === 'description'" class="prose max-w-none">
                {{-- Sanitised on render as well as on submit. The matching
                     boundary rule belongs in Store\Product\StoreProductRequest,
                     which is owned by another agent; until it lands, and for
                     rows written before it existed, this is the only place the
                     value stops being executable. --}}
                {!! \App\Rules\SafeHtml::sanitize($product->description ?? '', \App\Rules\SafeHtml::PROFILE_CONTENT) !!}
            </div>
            @if(setting('reviews_enabled', true))
                <div x-show="activeTab === 'reviews'">
                    @auth
                        <a href="{{ route('customer.reviews.create', $product->slug) }}" class="btn btn-primary mb-6">Write a Review</a>
                    @endauth

                    @if($product->reviews && count($product->reviews))
                        <div class="space-y-6">
                            @foreach($product->reviews as $review)
                                <div class="border-b hairline pb-6">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="flex">
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= $review->rating)
                                                    <i class="fa-solid fa-star text-base text-[var(--color-red)]"></i>
                                                @else
                                                    <i class="fa-regular fa-star text-base text-[var(--color-line)]"></i>
                                                @endif
                                            @endfor
                                        </div>
                                        <span class="font-medium text-[var(--color-ink)] text-sm">{{ $review->user->name ?? 'Anonymous' }}</span>
                                        <span class="text-[var(--color-steel)] text-sm">{{ $review->created_at->diffForHumans() }}</span>
                                    </div>
                                    @if($review->title)
                                        <h4 class="font-medium text-[var(--color-ink)]">{{ $review->title }}</h4>
                                    @endif
                                    <p class="text-[var(--color-ink-soft)] mt-1">{{ $review->comment }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-[var(--color-steel)]">No reviews yet. Be the first to review this product!</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Related Products --}}
    @if(isset($relatedProducts) && count($relatedProducts))
        <section class="mt-12">
            <h2 class="font-display text-2xl font-bold text-[var(--color-ink)] mb-6">Related Products</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach($relatedProducts as $product)
                    @include('components.store.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif
</div>

<script nonce="{{ $cspNonce }}">
let selectedVariantId = null;
let selectedVariantStock = null;
let selectedAttributeValues = {};
let isAddingToCart = false;
const currencySymbol = '{{ currency_symbol() }}';

function selectVariant(variantId) {
    const btn = document.querySelector(`[data-variant-id="${variantId}"]`);
    if (!btn || btn.disabled) return;

    selectedVariantId = variantId;
    selectedVariantStock = parseInt(btn.dataset.variantStock || '0');
    const variantPrice = parseFloat(btn.dataset.variantPrice || '0');
    const variantComparePrice = parseFloat(btn.dataset.variantComparePrice || '0');
    const image = btn.dataset.variantImage || null;

    document.querySelectorAll('.variant-btn').forEach(function(el) {
        el.classList.remove('border-[var(--color-ink)]', 'bg-[var(--color-paper-dim)]');
        el.classList.add('border-[var(--color-line)]');
    });
    btn.classList.remove('border-[var(--color-line)]');
    btn.classList.add('border-[var(--color-ink)]', 'bg-[var(--color-paper-dim)]');

    document.getElementById('variant-error')?.classList.add('hidden');

    let finalPrice = variantPrice;
    let originalPrice = variantComparePrice > 0 ? variantComparePrice : variantPrice;

                @if($activeFlashSale)
                    const flashPrice = parseFloat('{{ $flashPrice ?? $activeFlashSale->sale_price }}' || '0');
                    if (flashPrice > 0) {
                        finalPrice = flashPrice;
                        if (originalPrice <= finalPrice) {
                            originalPrice = variantPrice;
                        }
                    }
                @elseif($activeCampaign)
                    const campaignPrice = parseFloat('{{ $campaignPrice ?? '' }}' || '0');
                    if (campaignPrice > 0) {
                        finalPrice = campaignPrice;
                        if (originalPrice <= finalPrice) {
                            originalPrice = variantPrice;
                        }
                    }
                @endif

    const priceEl = document.getElementById('product-price');
    if (priceEl && finalPrice > 0) {
        priceEl.textContent = currencySymbol + ' ' + finalPrice.toFixed(2);
    }

    updateComparePrice(originalPrice, finalPrice);
    updateVariantImage(image);

    const qtyInput = document.getElementById('quantity');
    if (selectedVariantStock !== null && selectedVariantStock > 0) {
        qtyInput.max = Math.min(selectedVariantStock, 99);
        if (parseInt(qtyInput.value) > qtyInput.max) {
            qtyInput.value = qtyInput.max;
        }
    }
}

function updateComparePrice(comparePrice, currentPrice) {
    const container = document.querySelector('.flex.items-baseline.gap-3.mt-4');
    if (!container) return;

    container.querySelectorAll('.line-through, .bg-\\[var\\(--color-red\\)\\]').forEach(el => el.remove());

    if (comparePrice > 0 && comparePrice > currentPrice) {
        const strike = document.createElement('span');
        strike.className = 'price text-xl text-[var(--color-steel)] line-through';
        strike.textContent = currencySymbol + ' ' + comparePrice.toFixed(2);
        container.appendChild(strike);

        const badge = document.createElement('span');
        badge.className = 'bg-[var(--color-red)] text-white text-sm font-medium px-2 py-1';
        badge.textContent = 'Save ' + currencySymbol + ' ' + (comparePrice - currentPrice).toFixed(2);
        container.appendChild(badge);
    }
}

function updateVariantImage(image) {
    if (!image) return;

    // Target the gallery by id: the first `[x-data]` on the page is the header
    // user menu, so the old selector sat on the wrong element and the variant
    // image was never swapped in.
    const gallery = document.getElementById('product-gallery');
    if (!gallery) return;

    const mainImg = gallery.querySelector('img');
    if (mainImg) {
        mainImg.src = '{{ asset('storage/') }}' + image;
    }
}

function selectAttributeValue() {
    // Invoked through the delegated `data-action` dispatcher, which applies the
    // handler with the clicked element as `this`; the old `btn` parameter was
    // never supplied, so `btn.closest` threw on every click.
    const btn = this;
    const attrId = btn.closest('[data-attribute-id]')?.dataset.attributeId;
    const valueId = btn.dataset.attributeValueId;

    if (!attrId) return;

    document.querySelectorAll(`[data-attribute-id="${attrId}"] .attribute-value-btn`).forEach(function(el) {
        el.classList.remove('border-[var(--color-ink)]', 'bg-[var(--color-paper-dim)]');
        el.classList.add('border-[var(--color-line)]');
    });
    btn.classList.remove('border-[var(--color-line)]');
    btn.classList.add('border-[var(--color-ink)]', 'bg-[var(--color-paper-dim)]');

    selectedAttributeValues[attrId] = valueId;
    selectedVariantId = null;
    selectedVariantStock = null;

    const totalAttrs = {{ $variantAttributes->count() }};
    const selectedCount = Object.keys(selectedAttributeValues).length;

    if (selectedCount === totalAttrs) {
        resolveVariantFromAttributes();
    }
}

function resolveVariantFromAttributes() {
    const productId = '{{ $product->getKey() }}';
    const params = new URLSearchParams();
    params.append('product_id', productId);
    for (const [attrId, valueId] of Object.entries(selectedAttributeValues)) {
        params.append(`attributes[${attrId}]`, valueId);
    }

    fetch(`{{ url('ajax/products/variants') }}?${params.toString()}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data?.variant) {
                const variant = data.data.variant;
                // Serialised models carry `_id`; there is no `id` attribute in JSON.
                selectedVariantId = variant._id;
                selectedVariantStock = variant.stock_quantity || 0;

                let finalPrice = parseFloat(data.data.price || 0);
                let originalPrice = parseFloat(data.data.compare_price || 0);
                if (originalPrice <= 0) {
                    originalPrice = finalPrice;
                }

                @if($activeFlashSale)
                    const flashPrice = parseFloat('{{ $flashPrice ?? $activeFlashSale->sale_price }}' || '0');
                    if (flashPrice > 0) {
                        finalPrice = flashPrice;
                        if (originalPrice <= finalPrice) {
                            originalPrice = parseFloat(data.data.price || 0);
                        }
                    }
                @elseif($activeCampaign)
                    const campaignPrice = parseFloat('{{ $campaignPrice ?? '' }}' || '0');
                    if (campaignPrice > 0) {
                        finalPrice = campaignPrice;
                        if (originalPrice <= finalPrice) {
                            originalPrice = parseFloat(data.data.price || 0);
                        }
                    }
                @endif

                const priceEl = document.getElementById('product-price');
                if (priceEl && finalPrice > 0) {
                    priceEl.textContent = currencySymbol + ' ' + finalPrice.toFixed(2);
                }

                if (originalPrice > finalPrice) {
                    updateComparePrice(originalPrice, finalPrice);
                } else if (data.data.compare_price) {
                    updateComparePrice(parseFloat(data.data.compare_price), finalPrice);
                }

                if (data.data.image) {
                    updateVariantImage(data.data.image);
                }

                const qtyInput = document.getElementById('quantity');
                if (selectedVariantStock > 0) {
                    qtyInput.max = Math.min(selectedVariantStock, 99);
                }

                document.getElementById('variant-error')?.classList.add('hidden');
            } else {
                selectedVariantId = null;
                selectedVariantStock = 0;
            }
        })
        .catch(() => {
            selectedVariantId = null;
            selectedVariantStock = 0;
        });
}

function updateQuantity(change) {
    const input = document.getElementById('quantity');
    const max = parseInt(input.max || 99);
    const newVal = parseInt(input.value) + change;
    if (newVal >= 1 && newVal <= max) input.value = newVal;
}

function addToCartFromProduct(productId) {
    const hasVariantAttributes = {{ $hasVariantAttributes ? 'true' : 'false' }};
    const hasVariants = {{ $product->variants && count($product->variants) ? 'true' : 'false' }};

    if (hasVariantAttributes && !selectedVariantId) {
        document.getElementById('variant-error')?.classList.remove('hidden');
        showToast('Please select all options', 'error');
        return;
    }
    if (!hasVariantAttributes && hasVariants && !selectedVariantId) {
        document.getElementById('variant-error')?.classList.remove('hidden');
        showToast('Please select a variant', 'error');
        return;
    }

    if (isAddingToCart) return;
    isAddingToCart = true;

    const quantity = document.getElementById('quantity').value;
    fetch('{{ url("ajax/cart/add") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ product_id: productId, quantity: parseInt(quantity), variant_id: selectedVariantId })
    }).then(r => {
        if (r.status === 419) {
            showToast('Session expired. Please refresh the page and try again.', 'error');
            return Promise.reject();
        }
        return r.json();
    }).then(data => {
        if (data && data.success) {
            updateCartCount();
            showToast('Added to cart!');
        } else if (data) {
            showToast(data.message || 'Failed to add to cart', 'error');
        }
    }).catch(() => {}).finally(() => {
        isAddingToCart = false;
    });
}

function buyNow(productId) {
    const hasVariantAttributes = {{ $hasVariantAttributes ? 'true' : 'false' }};
    const hasVariants = {{ $product->variants && count($product->variants) ? 'true' : 'false' }};

    if (hasVariantAttributes && !selectedVariantId) {
        document.getElementById('variant-error')?.classList.remove('hidden');
        showToast('Please select all options', 'error');
        return;
    }
    if (!hasVariantAttributes && hasVariants && !selectedVariantId) {
        document.getElementById('variant-error')?.classList.remove('hidden');
        showToast('Please select a variant', 'error');
        return;
    }

    const quantity = document.getElementById('quantity').value;
    fetch('{{ url("ajax/cart/add") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ product_id: productId, quantity: parseInt(quantity), variant_id: selectedVariantId })
    }).then(r => {
        if (r.status === 419) {
            showToast('Session expired. Please refresh the page and try again.', 'error');
            window.location.href = '{{ route("login") }}';
            return Promise.reject();
        }
        return r.json();
    }).then(data => {
        if (data && data.success) {
            window.location.href = '{{ route("checkout") }}';
        } else if (data) {
            showToast(data.message || 'Failed to add to cart', 'error');
        }
    }).catch(() => {});
}

function addToWishlist(productId) {
    fetch('{{ url("ajax/wishlist/toggle") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ product_id: productId })
    }).then(r => {
        if (r.status === 401) {
            showToast('Please login to use wishlist', 'error');
            window.location.href = '{{ route('login') }}';
            return Promise.reject();
        }
        return r.json();
    }).then(data => {
        if (data.success) {
            showToast(data.message);
        } else {
            showToast(data.message || 'Failed', 'error');
        }
    }).catch(() => {});
}
</script>
@endsection