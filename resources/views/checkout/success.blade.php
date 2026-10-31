@extends('layouts.store')

@section('title', 'Order Status')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-16">
    {{-- Status Header --}}
    <div class="text-center mb-10">
        @php
            $isPaid = $order->payment_status?->value === 'paid';
            $isFailed = $order->payment_status?->value === 'failed' || $order->payment_status?->value === 'cancelled';
            $isPendingOnline = $order->payment_method === 'online' && $order->payment_status?->value === 'pending';
            $isCOD = $order->payment_method === 'cash_on_delivery';
        @endphp

        @if($isPaid)
            <div class="w-20 h-20 border-2 border-[#1B7A43] rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-check text-4xl text-[#1B7A43]"></i>
            </div>
            <h1 class="font-display text-3xl font-bold text-[var(--color-ink)] mb-2">Thank you for your order!</h1>
            <p class="text-[var(--color-steel)]">Your payment has been confirmed and your order is being processed.</p>
        @elseif($isFailed)
            <div class="w-20 h-20 border-2 border-[var(--color-red)] rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-xmark text-4xl text-[var(--color-red)]"></i>
            </div>
            <h1 class="font-display text-3xl font-bold text-[var(--color-ink)] mb-2">Payment Not Completed</h1>
            <p class="text-[var(--color-steel)]">Your payment was not successful. {{ session('error') ?? 'Please try again or choose a different payment method.' }}</p>
        @elseif($isPendingOnline)
            <div class="w-20 h-20 border-2 border-[var(--color-ink-soft)] rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-clock text-4xl text-[var(--color-ink-soft)]"></i>
            </div>
            <h1 class="font-display text-3xl font-bold text-[var(--color-ink)] mb-2">Payment Pending</h1>
            <p class="text-[var(--color-steel)]">Your payment is being processed. Please complete the payment on the <a href="{{ route('stripe.payment', $order->order_number) }}" class="text-[var(--color-red)] hover:underline">payment page</a>.</p>
        @elseif($isCOD)
            <div class="w-20 h-20 border-2 border-[#1B7A43] rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-check text-4xl text-[#1B7A43]"></i>
            </div>
            <h1 class="font-display text-3xl font-bold text-[var(--color-ink)] mb-2">Order Placed Successfully!</h1>
            <p class="text-[var(--color-steel)]">Your order has been placed. We'll confirm it within 24 hours.</p>
        @else
            <div class="w-20 h-20 border-2 border-[#1B7A43] rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-check text-4xl text-[#1B7A43]"></i>
            </div>
            <h1 class="font-display text-3xl font-bold text-[var(--color-ink)] mb-2">Thank you for your order!</h1>
            <p class="text-[var(--color-steel)]">Your order has been placed successfully.</p>
        @endif

        @if(session('error'))
            <div class="mt-4 p-3 bg-[var(--color-red)]/10 border border-[var(--color-red)]/20 rounded text-[var(--color-red)] text-sm">
                {{ session('error') }}
            </div>
        @endif
    </div>

    @if(isset($order))
        {{-- Order Summary --}}
        <div class="border hairline p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-sm text-[var(--color-steel)] mb-1">Order Number</p>
                    <p class="text-xl font-bold text-[var(--color-red)]">#{{ $order->order_number }}</p>
                </div>
                <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] capitalize">{{ $order->status?->label() ?? 'Unknown' }}</span>
            </div>

            <div class="border-t hairline pt-4 space-y-2 text-sm">
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
        </div>

        {{-- Printable Order Details --}}
        <div class="border hairline p-6 mb-6 print-only">
            <h3 class="font-semibold text-[var(--color-ink)] mb-4">Order Receipt</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[var(--color-steel)]">Order Number</span>
                    <span class="font-medium text-[var(--color-ink)]">#{{ $order->order_number }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[var(--color-steel)]">Date</span>
                    <span class="font-medium text-[var(--color-ink)]">{{ $order->created_at->format('M d, Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[var(--color-steel)]">Status</span>
                    <span class="font-medium capitalize text-[var(--color-ink)]">{{ $order->status?->label() ?? 'Unknown' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[var(--color-steel)]">Payment Method</span>
                    <span class="font-medium text-[var(--color-ink)]">{{ $order->payment_method_label }}</span>
                </div>
                <div class="border-t hairline pt-3 mt-3">
                    <p class="font-medium text-[var(--color-ink)] mb-2">Items</p>
                    <div class="space-y-2">
                        @foreach($order->items ?? [] as $item)
                            <div class="flex justify-between">
                                <span class="text-[var(--color-steel)]">{{ $item->product->name ?? ($item->product_name ?? 'Item') }} &times; {{ $item->quantity }}</span>
                                <span class="font-medium text-[var(--color-ink)]">{{ format_currency($item->line_total ?? ($item->quantity * $item->unit_price)) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="border-t hairline pt-3 mt-3 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-[var(--color-steel)]">Subtotal</span>
                        <span class="font-medium text-[var(--color-ink)]"> {{ format_currency($order->subtotal ?? 0) }}</span>
                    </div>
                    @if(($order->discount ?? 0) > 0)
                        <div class="flex justify-between">
                            <span class="text-[var(--color-steel)]">Discount</span>
                            <span class="font-medium text-[#1B7A43]">-{{ format_currency($order->discount) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-[var(--color-steel)]">Shipping</span>
                        <span class="font-medium text-[var(--color-ink)]">{{ format_currency($order->shipping_cost ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between text-base font-bold border-t hairline pt-2">
                        <span class="text-[var(--color-ink)]">Total</span>
                        <span class="text-[var(--color-ink)]">{{ format_currency($order->grand_total ?? 0) }}</span>
                    </div>
                </div>
                @if($order->shippingAddress ?? null)
                    <div class="border-t hairline pt-3 mt-3">
                        <p class="font-medium text-[var(--color-ink)] mb-1">Shipping Address</p>
                        <p class="text-[var(--color-steel)]">{{ $order->shippingAddress->full_name }}</p>
                        <p class="text-[var(--color-steel)]">{{ $order->shippingAddress->address_line_1 }}</p>
                        @if($order->shippingAddress->address_line_2)
                            <p class="text-[var(--color-steel)]">{{ $order->shippingAddress->address_line_2 }}</p>
                        @endif
                         <p class="text-[var(--color-steel)]">{{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }} {{ $order->shippingAddress->postal_code }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Next Steps --}}
        <div class="bg-[var(--color-paper-dim)] p-6 mb-6">
            <h3 class="font-semibold text-[var(--color-ink)] mb-3">What happens next?</h3>
            <div class="space-y-3 text-sm text-[var(--color-steel)]">
                @if($isPaid)
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-red)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">1</div>
                        <p>Your payment has been confirmed. We'll prepare your order for shipment.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-ink-soft)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">2</div>
                        <p>You'll receive an email confirmation with your order details and tracking information.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-steel)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">3</div>
                        <p>Track your order status anytime from your account dashboard.</p>
                    </div>
                @elseif($isFailed)
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-red)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">1</div>
                        <p>Your payment was not completed. You can <a href="{{ route('stripe.payment', $order->order_number) }}" class="text-[var(--color-red)] hover:underline">try again</a> or choose a different payment method.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-ink-soft)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">2</div>
                        <p>If you continue to experience issues, please contact our support team.</p>
                    </div>
                @elseif($isPendingOnline)
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-ink-soft)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">1</div>
                        <p>Complete your payment on the <a href="{{ route('stripe.payment', $order->order_number) }}" class="text-[var(--color-red)] hover:underline">payment page</a>.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-ink-soft)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">2</div>
                        <p>If you encounter issues, you can choose a different payment method.</p>
                    </div>
                @elseif($isCOD)
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-red)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">1</div>
                        <p>We'll review your order and confirm it within <span class="font-medium text-[var(--color-ink)]">24 hours</span>.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-ink-soft)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">2</div>
                        <p>You'll receive an email confirmation with your order details and tracking information.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-steel)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">3</div>
                        <p>Track your order status anytime from your account dashboard.</p>
                    </div>
                @else
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-red)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">1</div>
                        <p>We'll review your order and confirm it within <span class="font-medium text-[var(--color-ink)]">24 hours</span>.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-ink-soft)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">2</div>
                        <p>You'll receive an email confirmation with your order details and tracking information.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-[var(--color-steel)] text-white flex items-center justify-center text-xs flex-shrink-0 mt-0.5">3</div>
                        <p>Track your order status anytime from your account dashboard.</p>
                    </div>
                @endif
                @guest
                    <div class="mt-4 p-3 bg-[var(--color-paper)] border hairline rounded text-xs text-[var(--color-steel)]">
                        <span class="font-medium text-[var(--color-ink)]">Ordered as a guest?</span>
                        Register an account with the same email to access your order history and track orders anytime.
                    </div>
                @endguest
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 justify-center mb-6">
            @auth
                <a href="{{ route('customer.orders.show', $order->getKey() ?? 0) }}" class="btn btn-primary">
                    <i class="fa-solid fa-box mr-2"></i> View Order Details
                </a>
            @else
                <a href="{{ route('order.track') }}" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i> Track Your Order
                </a>
            @endauth
            <button type="button" data-action="printOrder" class="btn btn-outline text-[var(--color-ink)]">
                <i class="fa-solid fa-print mr-2"></i> Print Order
            </button>
            <a href="{{ route('shop') }}" class="btn btn-outline text-[var(--color-ink)]">
                <i class="fa-solid fa-bag-shopping mr-2"></i> Continue Shopping
            </a>
        </div>

        {{-- Help Box --}}
        <div class="border hairline p-4 text-sm text-[var(--color-steel)]">
            <p class="font-medium text-[var(--color-ink)] mb-1">Need help?</p>
            <p>If you have any questions about your order, please contact our support team at <a href="mailto:{{ setting('store_email') }}" class="text-[var(--color-red)] hover:underline">{{ setting('store_email') }}</a> or call us at <a href="tel:{{ setting('store_phone') }}" class="text-[var(--color-red)] hover:underline">{{ setting('store_phone') }}</a>.</p>
        </div>
    @endif
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .print-only, .print-only * {
            visibility: visible;
        }
        .print-only {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 20px;
        }
        .no-print {
            display: none !important;
        }
    }
</style>

<script nonce="{{ $cspNonce }}">
    function printOrder() {
        window.print();
    }
</script>
@endsection