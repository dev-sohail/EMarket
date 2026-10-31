@extends('layouts.store')

@section('title', 'Complete Payment')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-16">
    <div class="text-center mb-10">
        <h1 class="font-display text-3xl font-bold text-[var(--color-ink)] mb-2">Complete Your Payment</h1>
        <p class="text-[var(--color-steel)]">Order #{{ $order->order_number }} — {{ format_currency($order->grand_total) }}</p>
    </div>

    <div class="border hairline p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm text-[var(--color-steel)] mb-1">Order Number</p>
                <p class="text-xl font-bold text-[var(--color-red)]">#{{ $order->order_number }}</p>
            </div>
            <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] capitalize">{{ $order->status?->label() ?? 'Unknown' }}</span>
        </div>

        <div class="border-t hairline pt-4 space-y-2 text-sm mb-6">
            <div class="flex justify-between">
                <span class="text-[var(--color-steel)]">Total Amount</span>
                <span class="font-medium text-[var(--color-ink)] price">{{ format_currency($order->grand_total) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[var(--color-steel)]">Payment Method</span>
                <span class="font-medium text-[var(--color-ink)]">{{ $order->payment_method_label }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[var(--color-steel)]">Items</span>
                <span class="font-medium text-[var(--color-ink)]">{{ $order->items->sum('quantity') }}</span>
            </div>
        </div>

        <div id="stripe-payment-form">
            <div id="card-element" class="border hairline rounded p-4 mb-4 min-h-[48px]"></div>
            <button id="pay-button" class="w-full bg-[var(--color-red)] text-white px-4 py-3 rounded font-medium hover:bg-[var(--color-red-dark)] transition">
                Pay {{ format_currency($order->grand_total) }}
            </button>
            <p id="card-errors" class="text-sm text-[var(--color-red)] mt-2" role="alert"></p>
        </div>

        <div id="payment-success" class="hidden text-center py-8">
            <div class="w-16 h-16 border-2 border-[#1B7A43] rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-check text-3xl text-[#1B7A43]"></i>
            </div>
            <h2 class="font-display text-2xl font-bold text-[var(--color-ink)] mb-2">Payment Successful!</h2>
            <p class="text-[var(--color-steel)] mb-6">Your order has been confirmed and will be processed shortly.</p>
            <a href="{{ route('customer.orders.show', $order->getKey()) }}" class="btn btn-primary">View Order Details</a>
        </div>
    </div>

    <div class="text-center">
        <form method="POST" action="{{ route('stripe.cancel', $order->order_number) }}" class="inline">
            @csrf
            <button type="submit" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-red)]">Cancel and choose different payment method</button>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script nonce="{{ $cspNonce }}">
(function() {
    const stripe = Stripe(@json($stripePublishableKey));
    const elements = stripe.elements();
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                '::placeholder': { color: '#aab7c4' },
            },
        },
    });
    cardElement.mount('#card-element');

    const payButton = document.getElementById('pay-button');
    const cardErrors = document.getElementById('card-errors');
    const paymentForm = document.getElementById('stripe-payment-form');
    const paymentSuccess = document.getElementById('payment-success');
    const statusUrl = '{{ route('stripe.status', $order->order_number) }}?tracking_token={{ $order->tracking_token }}';
    const successUrl = '{{ route('checkout.success', $order->order_number) }}';
    // Stripe client secrets are designed for client-side use by Stripe.js
    const clientSecret = @json($clientSecret);
    let pollCount = 0;
    const MAX_POLLS = 15; // 30 seconds max

    payButton.addEventListener('click', async function(event) {
        event.preventDefault();
        payButton.disabled = true;
        payButton.textContent = 'Processing...';
        cardErrors.textContent = '';
        pollCount = 0;

        try {
            const { error, paymentIntent } = await stripe.confirmCardPayment(
                clientSecret,
                { payment_method: { card: cardElement } }
            );

            if (error) {
                cardErrors.textContent = error.message;
                payButton.disabled = false;
                payButton.textContent = 'Pay {{ format_currency($order->grand_total) }}';
            } else if (paymentIntent.status === 'succeeded') {
                // Payment succeeded on Stripe side — show success immediately
                // and redirect. Server webhook will update order status asynchronously.
                paymentForm.classList.add('hidden');
                paymentSuccess.classList.remove('hidden');
                setTimeout(() => {
                    window.location.href = successUrl;
                }, 1500);
            } else if (paymentIntent.status === 'requires_action') {
                pollStatus();
            } else if (paymentIntent.status === 'processing') {
                pollStatus();
            } else {
                cardErrors.textContent = 'Payment could not be completed. Please try again.';
                payButton.disabled = false;
                payButton.textContent = 'Pay {{ format_currency($order->grand_total) }}';
            }
        } catch (error) {
            cardErrors.textContent = error.message;
            payButton.disabled = false;
            payButton.textContent = 'Pay {{ format_currency($order->grand_total) }}';
        }
    });

    function pollStatus() {
        payButton.disabled = true;
        payButton.textContent = 'Verifying payment...';

        const poll = async () => {
            if (pollCount >= MAX_POLLS) {
                cardErrors.textContent = 'Payment verification timed out. Please check your order status or contact support.';
                payButton.disabled = false;
                payButton.textContent = 'Pay {{ format_currency($order->grand_total) }}';
                return;
            }
            pollCount++;

            try {
                const response = await fetch(statusUrl, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) {
                    throw new Error('Status check failed');
                }
                const data = await response.json();
                if (data.payment_status === 'paid') {
                    paymentForm.classList.add('hidden');
                    paymentSuccess.classList.remove('hidden');
                    setTimeout(() => {
                        window.location.href = successUrl;
                    }, 1500);
                } else if (data.payment_status === 'failed' || data.payment_status === 'cancelled') {
                    cardErrors.textContent = 'Payment was not successful. Please try again or choose a different payment method.';
                    payButton.disabled = false;
                    payButton.textContent = 'Pay {{ format_currency($order->grand_total) }}';
                } else if (data.payment_status === 'pending' && (data.status === 'pending' || data.status === 'confirmed')) {
                    setTimeout(poll, 2000);
                } else {
                    setTimeout(poll, 2000);
                }
            } catch (error) {
                if (pollCount < MAX_POLLS) {
                    setTimeout(poll, 3000);
                } else {
                    cardErrors.textContent = 'Payment verification timed out. Please check your order status or contact support.';
                    payButton.disabled = false;
                    payButton.textContent = 'Pay {{ format_currency($order->grand_total) }}';
                }
            }
        };
        poll();
    }
})();
</script>
@endpush
@endsection