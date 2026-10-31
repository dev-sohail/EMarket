@extends('layouts.store')

@section('title', 'Checkout')

@section('content')
{{-- Hero --}}
<section class="bg-[var(--color-ink)] text-white">
    <div class="max-w-6xl mx-auto px-4 py-12 sm:py-16">
        <nav class="flex items-center gap-2 text-sm text-white/55 mb-6">
            <a href="{{ route('home') }}" class="hover:text-white transition">Home</a>
            <span>/</span>
            <a href="{{ route('cart') }}" class="hover:text-white transition">Cart</a>
            <span>/</span>
            <span class="text-white">Checkout</span>
        </nav>
        <span class="eyebrow text-[var(--color-red)]">Checkout</span>
        <h1 class="font-display text-3xl sm:text-4xl font-bold mt-2 leading-tight">Checkout</h1>
    </div>
</section>

<div class="max-w-6xl mx-auto px-4 py-10">
    @if(session('error'))
        <div class="border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] px-4 py-3 mb-4">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="border hairline bg-[var(--color-paper-dim)] text-[var(--color-red)] px-4 py-3 mb-4">
            <p class="font-semibold mb-2">Please fix the following errors:</p>
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Checkout Form --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('checkout.process') }}" id="checkout-form" novalidate>
                @csrf
                <input type="hidden" name="coupon_code" id="coupon_code_input" value="{{ $couponCode ?? '' }}">
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                {{-- Idempotency key is per-session/per-cart and the page is not cached; safe to expose --}}

                {{-- Guest Information --}}
                @guest
                    <div class="border hairline p-6 mb-6">
                        <h2 class="font-semibold text-[var(--color-ink)] mb-4">Contact Information</h2>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="guest_name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Full Name *</label>
                                    <input type="text" name="name" id="guest_name" value="{{ old('name') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('name') border-[var(--color-red)] @enderror">
                                    @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="guest_email" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Email *</label>
                                    <input type="email" name="email" id="guest_email" value="{{ old('email') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('email') border-[var(--color-red)] @enderror">
                                    @error('email') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div>
                                <label for="guest_phone" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Phone *</label>
                                <input type="tel" name="phone" id="guest_phone" value="{{ old('phone') }}" required class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('phone') border-[var(--color-red)] @enderror">
                                @error('phone') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endguest

                {{-- Shipping Address --}}
                <div class="border hairline p-6 mb-6">
                    <h2 class="font-semibold text-[var(--color-ink)] mb-4">Shipping Address</h2>
                    @auth
                        @php
                            $addresses = auth()->user()->customer?->addresses ?? collect();
                        @endphp
                        @if($addresses->isNotEmpty())
                            <div class="space-y-3 mb-4">
                                @foreach($addresses as $address)
                                <label class="flex items-start gap-3 p-3 border cursor-pointer {{ $address->is_default ? 'border-[var(--color-ink)] bg-[var(--color-paper-dim)]' : 'hover:border-[var(--color-ink)]' }}">
                                    <input type="radio" name="shipping_address_id" value="{{ $address->getKey() }}" data-use-new-address="0" {{ $address->is_default ? 'checked' : '' }} class="mt-1 text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                        <div class="text-sm">
                                            <p class="font-medium text-[var(--color-ink)]">{{ $address->full_name }}</p>
                                            @if($address->phone)
                                                <p class="text-[var(--color-steel)] text-xs">{{ $address->phone }}</p>
                                            @endif
                                            <p class="text-[var(--color-ink-soft)]">{{ $address->address_line_1 }}</p>
                                            @if($address->address_line_2)
                                                <p class="text-[var(--color-ink-soft)]">{{ $address->address_line_2 }}</p>
                                            @endif
                                             <p class="text-[var(--color-ink-soft)]">{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            <button type="button" id="use-new-address-btn" class="text-[var(--color-red)] text-sm font-medium hover:text-[var(--color-red-dark)] mb-4 inline-block">+ Use a different address</button>
                        @endif
                    @endauth

                    <input type="radio" name="shipping_use_new_address" value="1" id="shipping_use_new_address" class="hidden">
                    <div id="new-address-form" class="space-y-4 {{ (auth()->check() && auth()->user()?->customer?->addresses?->isNotEmpty()) ? 'hidden' : '' }}">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="shipping_first_name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">First Name</label>
                                <input type="text" name="shipping_first_name" id="shipping_first_name" value="{{ old('shipping_first_name', trim(explode(' ', auth()->user()?->name ?? '')[0])) ?? ($guestAddress['full_name'] ?? '') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('shipping_first_name') border-[var(--color-red)] @enderror">
                                @error('shipping_first_name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="shipping_last_name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Last Name</label>
                                <input type="text" name="shipping_last_name" id="shipping_last_name" value="{{ old('shipping_last_name', trim(explode(' ', auth()->user()?->name ?? '')[1] ?? '')) ?? ($guestAddress['full_name'] ?? '') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('shipping_last_name') border-[var(--color-red)] @enderror">
                                @error('shipping_last_name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label for="shipping_address" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Address *</label>
                            <input type="text" name="shipping_address" id="shipping_address" value="{{ old('shipping_address', $guestAddress['address_line_1'] ?? '') }}" placeholder="Street address" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('shipping_address') border-[var(--color-red)] @enderror">
                            @error('shipping_address') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="shipping_address2" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Apartment, suite, etc. (optional)</label>
                            <input type="text" name="shipping_address2" id="shipping_address2" value="{{ old('shipping_address2', $guestAddress['address_line_2'] ?? '') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)]">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="shipping_city" class="block text-sm font-medium text-[var(--color-ink)] mb-1">City *</label>
                                <input type="text" name="shipping_city" id="shipping_city" value="{{ old('shipping_city', $guestAddress['city'] ?? '') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('shipping_city') border-[var(--color-red)] @enderror">
                                @error('shipping_city') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="shipping_state" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Province / Canton *</label>
                                <input type="text" name="shipping_state" id="shipping_state" value="{{ old('shipping_state', $guestAddress['state'] ?? '') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('shipping_state') border-[var(--color-red)] @enderror">
                                @error('shipping_state') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="shipping_zip_code" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Postal Code *</label>
                                <input type="text" name="shipping_zip_code" id="shipping_zip_code" value="{{ old('shipping_zip_code', $guestAddress['postal_code'] ?? '') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('shipping_zip_code') border-[var(--color-red)] @enderror">
                                @error('shipping_zip_code') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="shipping_phone" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Phone *</label>
                                <input type="tel" name="shipping_phone" id="shipping_phone" value="{{ old('shipping_phone', $guestAddress['phone'] ?? '') }}" placeholder="+92 300 1234567" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('shipping_phone') border-[var(--color-red)] @enderror">
                                @error('shipping_phone') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Shipping Method --}}
                <div class="border hairline p-6 mb-6">
                    <h2 class="font-semibold text-[var(--color-ink)] mb-4">Shipping Method *</h2>
                    @if($shippingMethods->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($shippingMethods as $method)
                                <label class="flex items-center justify-between p-3 border cursor-pointer hover:border-[var(--color-ink)] focus-within:border-[var(--color-ink)] focus-within:ring-1 focus-within:ring-[var(--color-red)]">
                                    <div class="flex items-center gap-3">
                                        <input type="radio" name="shipping_method_id" value="{{ $method->_id }}" {{ $loop->first ? 'checked' : '' }} required class="text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                        <div>
                                            <p class="font-medium text-[var(--color-ink)]">{{ $method->name }}</p>
                                            <p class="text-sm text-[var(--color-steel)]">{{ $method->description ?? '' }}</p>
                                        </div>
                                    </div>
                                            <span class="text-sm font-medium text-[var(--color-ink)] price">{{ format_currency($method->calculateRate($cartWeight, $pricing['subtotal'])) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="text-[var(--color-steel)]">No shipping methods available.</p>
                    @endif
                    @error('shipping_method_id')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Payment Method --}}
                <div class="border hairline p-6 mb-6">
                    <h2 class="font-semibold text-[var(--color-ink)] mb-4">Payment Method *</h2>
                    <p class="mb-4 text-sm text-[var(--color-steel)]">Follow the selected method's payment instructions.</p>
                    <div class="space-y-3">
                        @forelse($paymentMethods ?? [] as $method)
                            <label for="payment_method_{{ $method->_id }}" class="flex items-start gap-3 p-3 border cursor-pointer {{ old('payment_method', $paymentMethods->first()?->code) === $method->code ? 'border-[var(--color-ink)] bg-[var(--color-paper-dim)]' : 'hover:border-[var(--color-ink)] focus-within:border-[var(--color-ink)] focus-within:ring-1 focus-within:ring-[var(--color-red)]' }}">
                                <input type="radio" name="payment_method" id="payment_method_{{ $method->_id }}" value="{{ $method->code }}" @checked(old('payment_method', $paymentMethods->first()?->code) === $method->code) required class="mt-1 text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                <div class="flex-1">
                                    <p class="font-medium text-[var(--color-ink)]">{{ $method->name }}</p>
                                    <p class="text-sm text-[var(--color-steel)]">{{ $method->description ?? '' }}</p>
                                    @if(! $method->is_cod && $method->stripe_enabled)
                                        <p class="text-xs text-[var(--color-steel)] mt-1">You will be redirected to complete payment securely with Stripe after placing the order.</p>
                                    @endif
                                </div>
                            </label>
                        @empty
                            <p class="text-[var(--color-steel)]">No payment methods available.</p>
                        @endforelse
                    </div>
                    @error('payment_method')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Notes --}}
                <div class="border hairline p-6 mb-6">
                    <label for="notes" class="block font-semibold text-[var(--color-ink)] mb-4">Order Notes (optional)</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Any special instructions for your order..." class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('notes') border-[var(--color-red)] @enderror">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                {{-- Place Order --}}
                <button type="submit" id="place-order-btn" @disabled(count($paymentMethods ?? []) === 0) class="btn btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="btn-text">Place Order</span>
                    <span class="btn-loading hidden flex items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        Processing...
                    </span>
                </button>

                <p class="text-xs text-[var(--color-steel)] text-center mt-4">By placing this order, you agree to our <a href="{{ route('page.show', 'terms') }}" class="text-[var(--color-red)] hover:underline">Terms</a> and <a href="{{ route('page.show', 'privacy-policy') }}" class="text-[var(--color-red)] hover:underline">Privacy Policy</a>.</p>
            </form>
        </div>

        {{-- Order Summary --}}
        <div class="lg:col-span-1">
            <div class="border hairline p-6 sticky top-24">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Order Summary</h2>

                @php
                    // Priced lines from PricingService::calculateCartTotal(), keyed by cart item.
                    // The summary must be the figures createOrder() charges, not
                    // cart_items.unit_price, which is a snapshot from add-to-cart time.
                    $pricedLines = collect($pricing['items'] ?? [])->keyBy('cart_item_id');
                @endphp

                <div class="space-y-3 max-h-60 overflow-y-auto mb-4">
                    @foreach($cart->items as $item)
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-[var(--color-paper-dim)] flex-shrink-0 flex items-center justify-center relative">
                                @if($item->product?->primary_image)
                                    <img src="{{ asset('storage/' . $item->product->primary_image) }}" alt="{{ $item->product->name ?? '' }}" class="w-full h-full object-cover" loading="lazy">
                                @else
                                    <i class="fa-solid fa-image text-lg text-[var(--color-line)]"></i>
                                @endif
                                <span class="absolute -top-1 -right-1 bg-[var(--color-ink)] text-white text-xs w-4 h-4 flex items-center justify-center">{{ $item->quantity }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-[var(--color-ink)] truncate">{{ $item->product->name ?? $item->product_name ?? 'Product' }}</p>
                            </div>
                            <p class="text-sm font-medium text-[var(--color-ink)] price">{{ format_currency($pricedLines->get((string) $item->getKey())['line_total'] ?? 0) }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Coupon --}}
                <div class="flex gap-2 mb-4">
                    <label for="coupon_input" class="sr-only">Coupon code</label>
                    <input type="text" name="coupon_code_display" id="coupon_input" placeholder="Coupon code" class="flex-1 border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                    <button type="button" data-action="applyCoupon" class="bg-[var(--color-ink)] text-white px-4 py-2 text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">Apply</button>
                </div>

                <div class="space-y-2 border-t hairline pt-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-[var(--color-steel)]">Subtotal</span>
                        <span class="price">{{ format_currency($pricing['subtotal'] ?? $cart->subtotal) }}</span>
                    </div>
                    <div id="discount-row" class="flex justify-between text-sm text-[var(--color-sale)]" style="display: none;">
                        <span>Discount</span>
                        <span id="discount-amount" class="price">-{{ format_currency(0) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold border-t hairline pt-2 text-[var(--color-ink)]">
                        <span>Total</span>
                        <span id="order-total" class="price">{{ format_currency($totals['grand_total'] ?? $totals['total'] ?? $pricing['subtotal'] ?? $cart->subtotal) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
const currencySymbol = @json(currency_symbol());

function applyCoupon() {
    const code = document.querySelector('input[name="coupon_code_display"]').value;
    if (!code) {
        alert('Please enter a coupon code.');
        return;
    }
    fetch('{{ url("ajax/checkout/apply-coupon") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ coupon_code: code })
    }).then(r => {
        // 4xx responses carry a user-facing message; only 5xx/network are generic.
        if (!r.ok && r.status >= 500) {
            alert('Failed to apply coupon. Please try again.');
            return null;
        }
        return r.json();
    }).then(data => {
        if (data && data.success) {
            const discount = data.data?.discount ?? data.discount ?? 0;
            const total = data.data?.total ?? data.total ?? 0;
            const freeShipping = data.data?.free_shipping ?? data.free_shipping ?? false;

            if (discount > 0) {
                document.getElementById('discount-row').style.display = 'flex';
                document.getElementById('discount-amount').textContent = '-' + currencySymbol + ' ' + Number(discount).toFixed(2);
            } else {
                document.getElementById('discount-row').style.display = 'none';
            }

            if (freeShipping) {
                document.getElementById('discount-row').style.display = 'flex';
                document.getElementById('discount-amount').textContent = 'Free Shipping';
            }

            document.getElementById('order-total').textContent = currencySymbol + ' ' + Number(total).toFixed(2);
            document.getElementById('coupon_code_input').value = code;
        } else if (data) {
            alert(data.message || 'Invalid coupon code.');
        }
    }).catch(() => alert('Failed to apply coupon. Please try again.'));
}

document.addEventListener('DOMContentLoaded', function() {
    const guestName = document.getElementById('guest_name');
    const guestEmail = document.getElementById('guest_email');
    const guestPhone = document.getElementById('guest_phone');
    const shippingFirstName = document.getElementById('shipping_first_name');
    const shippingLastName = document.getElementById('shipping_last_name');
    const shippingPhone = document.getElementById('shipping_phone');
    const newAddressForm = document.getElementById('new-address-form');
    const useNewAddressBtn = document.getElementById('use-new-address-btn');
    const useNewAddressRadio = document.getElementById('shipping_use_new_address');
    const savedAddressRadios = document.querySelectorAll('input[name="shipping_address_id"]');

    function updateAddressMode() {
        const selectedSaved = document.querySelector('input[name="shipping_address_id"]:checked');
        const newAddressForm = document.getElementById('new-address-form');
        const useNewAddressRadio = document.getElementById('shipping_use_new_address');
        const newAddressInputs = newAddressForm ? newAddressForm.querySelectorAll('input, select, textarea') : [];
        
        if (selectedSaved) {
            newAddressForm.classList.add('hidden');
            useNewAddressRadio.checked = false;
            newAddressInputs.forEach(input => input.disabled = true);
        } else {
            newAddressForm.classList.remove('hidden');
            useNewAddressRadio.checked = true;
            newAddressInputs.forEach(input => input.disabled = false);
        }
    }

    savedAddressRadios.forEach(radio => {
        radio.addEventListener('change', updateAddressMode);
    });

    updateAddressMode();

    if (useNewAddressBtn && newAddressForm) {
        useNewAddressBtn.addEventListener('click', function() {
            savedAddressRadios.forEach(radio => {
                radio.checked = false;
            });
            newAddressForm.classList.remove('hidden');
            useNewAddressRadio.checked = true;
            const first = document.querySelector('#new-address-form input');
            if (first) first.focus();
            newAddressForm.querySelectorAll('input, select, textarea').forEach(input => input.disabled = false);
        });
    }

    if (guestName && shippingFirstName && shippingLastName) {
        guestName.addEventListener('input', function() {
            const parts = this.value.trim().split(' ');
            shippingFirstName.value = parts[0] || '';
            shippingLastName.value = parts.slice(1).join(' ') || '';
        });
    }

    if (guestPhone && shippingPhone) {
        guestPhone.addEventListener('input', function() {
            shippingPhone.value = this.value;
        });
    }

    // Loading state for place order button
    const placeOrderBtn = document.getElementById('place-order-btn');
    const checkoutForm = document.getElementById('checkout-form');
    if (placeOrderBtn && checkoutForm) {
        checkoutForm.addEventListener('submit', function() {
            if (this.checkValidity()) {
                placeOrderBtn.disabled = true;
                placeOrderBtn.querySelector('.btn-text').classList.add('hidden');
                placeOrderBtn.querySelector('.btn-loading').classList.remove('hidden');
            }
        });
    }
});
</script>
@endsection