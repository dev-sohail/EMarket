@extends('layouts.admin')
@use(App\Enums\PaymentStatus)
@section('title', 'Order #' . ($order->order_number ?? ''))
@section('header')
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Order #{{ $order->order_number ?? '' }}</h1>
    <a href="{{ route('admin.orders.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Orders</a>
</div>
@endsection
@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    {{-- Admin Header with Quick Actions --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="font-display text-3xl font-bold text-[var(--color-ink)] mb-1">Order #{{ $order->order_number }}</h1>
            <p class="text-[var(--color-steel)]">Created: {{ $order->created_at->format('F j, Y g:i A') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.order-processor.picking-list', $order) }}" class="inline-flex items-center gap-2 bg-white border hairline px-4 py-2 rounded text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
                <i class="fa-solid fa-list"></i> Picking List
            </a>
            <a href="{{ route('admin.orders.print', $order) }}" class="inline-flex items-center gap-2 bg-white border hairline px-4 py-2 rounded text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
                <i class="fa-solid fa-print"></i> Print Order
            </a>
            @if($order->status?->value !== 'cancelled')
            <a href="{{ route('admin.order-processor.label', $order) }}" class="inline-flex items-center gap-2 bg-white border hairline px-4 py-2 rounded text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
                <i class="fa-solid fa-tag"></i> Shipping Label
            </a>
            @endif
            <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center gap-2 bg-[var(--color-red)] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                <i class="fa-solid fa-arrow-left"></i> Back to Orders
            </a>
        </div>
    </div>

    {{-- Key Order Status Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4">
            <p class="text-sm text-[var(--color-steel)] mb-1">Order Status</p>
            <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full bg-blue-100 text-blue-800">{{ $order->status?->label() ?? 'Unknown' }}</span>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4">
            <p class="text-sm text-[var(--color-steel)] mb-1">Payment Status</p>
            <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full {{ $order->payment_status?->value === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                {{ $order->payment_status?->label() ?? 'Pending' }}
            </span>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4">
            <p class="text-sm text-[var(--color-steel)] mb-1">Fulfillment Status</p>
            <span class="inline-flex px-3 py-1 text-sm font-medium rounded-full bg-purple-100 text-purple-800">{{ $order->fulfillment_status?->label() ?? 'Pending' }}</span>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4">
            <p class="text-sm text-[var(--color-steel)] mb-1">Total Amount</p>
            <p class="text-xl font-bold text-[var(--color-ink)]">{{ format_currency($order->grand_total) }}</p>
        </div>
    </div>

    {{-- Quick Status Update Bar --}}
    @if($order->status?->value !== 'cancelled')
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4 mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="font-semibold text-[var(--color-ink)]">Quick Update</h3>
                <p class="text-sm text-[var(--color-steel)]">Change order status or update shipping information</p>
            </div>
            <form method="POST" action="{{ route('admin.orders.status', $order->getKey()) }}" class="flex flex-col sm:flex-row flex-wrap items-start sm:items-center gap-3">
                @csrf @method('PUT')
                <select name="status" class="border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)] @error('status') border-[var(--color-red)] @enderror min-w-[180px]">
                    @foreach(['pending','confirmed','processing','ready_to_ship','shipped','out_for_delivery','delivered','cancelled'] as $s)
                        <option value="{{ $s }}" {{ $order->status?->value === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
                <input type="text" name="note" placeholder="Add status note (optional)" class="border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)] flex-1 min-w-[200px] @error('note') border-[var(--color-red)] @enderror" value="{{ old('note') }}">
                <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition whitespace-nowrap">Update Status</button>
            </form>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Left Column - Customer & Addresses --}}
        <div class="space-y-6">
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-lg text-[var(--color-ink)] mb-4">Customer Information</h2>
                <div class="text-sm text-[var(--color-steel)] space-y-2">
                    @php
                        $customer = $order->customer;
                        // Always access customer data through the customer relationship, never directly from users table
                        $name = $customer ? trim($customer->first_name . ' ' . $customer->last_name) : null;
                        $email = $customer?->email;
                        $phone = $customer?->phone ?? null;
                        $id = $customer?->getKey() ?? null;
                        $idLabel = $customer ? 'Customer ID' : null;
                        $isActive = $customer?->is_active ?? null;
                    @endphp

                    @if($name)
                        <p class="font-semibold text-[var(--color-ink)] text-base">{{ $name }}</p>
                    @endif
                    @if($email !== null && $email !== '')
                        <p class="flex items-center gap-2"><i class="fa-solid fa-envelope w-4"></i> {{ $email }}</p>
                    @endif
                    @if($phone)
                        <p class="flex items-center gap-2"><i class="fa-solid fa-phone w-4"></i> {{ $phone }}</p>
                    @endif
                    @if($isActive !== null)
                        <p class="flex items-center gap-2"><i class="fa-solid fa-circle w-2 {{ $isActive ? 'text-green-500' : 'text-red-500' }}"></i> {{ $isActive ? 'Active' : 'Inactive' }}</p>
                    @endif
                    @if($id && $idLabel)
                        <hr class="my-2 border-[var(--color-paper-dim)]">
                        <p class="text-xs text-[var(--color-steel)] opacity-75">{{ $idLabel }}: {{ $id }}</p>
                    @elseif(!$name && !$email && !$phone)
                        <p class="text-[var(--color-steel)] italic">Guest — no account info available</p>
                    @endif
                </div>
            </div>

            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-lg text-[var(--color-ink)] mb-4">Shipping Address</h2>
                <div class="text-sm text-[var(--color-steel)] space-y-1">
                    @if($order->shippingAddress)
                        <p class="font-semibold text-[var(--color-ink)]">{{ $order->shippingAddress->full_name }}</p>
                        <p>{{ $order->shippingAddress->address_line_1 }}</p>
                        @if($order->shippingAddress->address_line_2)
                            <p>{{ $order->shippingAddress->address_line_2 }}</p>
                        @endif
                        <p>{{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }} {{ $order->shippingAddress->postal_code }}</p>
                        @if($order->shippingAddress->phone)
                            <hr class="my-2 border-[var(--color-paper-dim)]">
                            <p class="flex items-center gap-2"><i class="fa-solid fa-phone w-4"></i> {{ $order->shippingAddress->phone }}</p>
                        @endif
                    @else
                        <p class="text-[var(--color-steel)] italic">No shipping address on file</p>
                    @endif
                </div>
            </div>

            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-lg text-[var(--color-ink)] mb-4">Billing Address</h2>
                <div class="text-sm text-[var(--color-steel)] space-y-1">
                    @if($order->billingAddress)
                        <p class="font-semibold text-[var(--color-ink)]">{{ $order->billingAddress->full_name }}</p>
                        <p>{{ $order->billingAddress->address_line_1 }}</p>
                        @if($order->billingAddress->address_line_2)
                            <p>{{ $order->billingAddress->address_line_2 }}</p>
                        @endif
                        <p>{{ $order->billingAddress->city }}, {{ $order->billingAddress->state }} {{ $order->billingAddress->postal_code }}</p>
                        @if($order->billingAddress->phone)
                            <hr class="my-2 border-[var(--color-paper-dim)]">
                            <p class="flex items-center gap-2"><i class="fa-solid fa-phone w-4"></i> {{ $order->billingAddress->phone }}</p>
                        @endif
                    @else
                        <p class="text-[var(--color-steel)] italic">Same as shipping address</p>
                    @endif
                </div>
            </div>

            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-lg text-[var(--color-ink)] mb-4">Payment Details</h2>
                <div class="text-sm text-[var(--color-steel)] space-y-3">
                    <div class="flex justify-between items-center">
                        <span>Payment Method:</span>
                        <span class="font-medium text-[var(--color-ink)]">{{ $order->payment_method_label ?? 'Unknown' }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Payment Status:</span>
                        <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full {{ $order->payment_status?->value === 'paid' ? 'bg-green-100 text-green-800' : ($order->payment_status?->value === 'refunded' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                            {{ $order->payment_status?->label() ?? 'Pending' }}
                        </span>
                    </div>
                    @if($order->transaction_id)
                    <hr class="border-[var(--color-paper-dim)]">
                    <div class="flex justify-between">
                        <span>Transaction ID:</span>
                        <span class="font-mono text-xs text-[var(--color-ink)]">{{ $order->transaction_id }}</span>
                    </div>
                    @endif
                    @if($order->stripe_payment_intent_id)
                    <div class="flex justify-between">
                        <span>Stripe Payment ID:</span>
                        <span class="font-mono text-xs text-[var(--color-ink)]">{{ $order->stripe_payment_intent_id }}</span>
                    </div>
                    @endif
                    @if($order->payment_date)
                    <div class="flex justify-between">
                        <span>Payment Date:</span>
                        <span class="font-medium text-[var(--color-ink)]">{{ $order->payment_date->format('F j, Y g:i A') }}</span>
                    </div>
                    @endif
                    <hr class="border-[var(--color-paper-dim)]">
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span class="font-medium text-[var(--color-ink)]">{{ format_currency($order->subtotal ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Shipping:</span>
                        <span class="font-medium text-[var(--color-ink)]">{{ format_currency($order->shipping_cost ?? 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tax:</span>
                        <span class="font-medium text-[var(--color-ink)]">{{ format_currency($order->tax ?? 0) }}</span>
                    </div>
                    @if(($order->discount ?? 0) > 0)
                    <div class="flex justify-between">
                        <span class="text-green-600">Discount{{ $order->coupon_code ? ' (' . $order->coupon_code . ')' : '' }}:</span>
                        <span class="font-medium text-green-600">-{{ format_currency($order->discount) }}</span>
                    </div>
                    @endif
                    @if(($order->additional_charges ?? 0) > 0)
                    <div class="flex justify-between">
                        <span>Additional Charges:</span>
                        <span class="font-medium text-[var(--color-ink)]">{{ format_currency($order->additional_charges) }}</span>
                    </div>
                    @endif
                    <hr class="border-[var(--color-paper-dim)]">
                    <div class="flex justify-between">
                        <span class="font-semibold text-[var(--color-ink)]">Grand Total:</span>
                        <span class="font-bold text-lg text-[var(--color-ink)]">{{ format_currency($order->grand_total ?? 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column - Order Items & Details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Shipping & Tracking - Moved to top for admin workflow --}}
            @if($order->status?->value !== 'cancelled')
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-lg text-[var(--color-ink)] mb-4">Shipping & Tracking</h2>
                <form method="POST" action="{{ route('admin.order-processor.shipping', $order) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Tracking Number</label>
                            <input type="text" name="tracking_number" value="{{ $order->tracking_number ?? '' }}" placeholder="Enter tracking number" class="w-full border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Carrier</label>
                            <select name="carrier" class="w-full border hairline bg-white px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                                <option value="">Select Carrier</option>
                                <option value="dhl" {{ ($order->carrier ?? '') === 'dhl' ? 'selected' : '' }}>DHL</option>
                                <option value="fedex" {{ ($order->carrier ?? '') === 'fedex' ? 'selected' : '' }}>FedEx</option>
                                <option value="ups" {{ ($order->carrier ?? '') === 'ups' ? 'selected' : '' }}>UPS</option>
                                <option value="aramex" {{ ($order->carrier ?? '') === 'aramex' ? 'selected' : '' }}>Aramex</option>
                                <option value="standard" {{ ($order->carrier ?? '') === 'standard' ? 'selected' : '' }}>Standard Post</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-[var(--color-steel)]">Fulfillment Status:</span>
                            <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">{{ $order->fulfillment_status?->label() ?? 'Pending' }}</span>
                        </div>
                        @if($order->tracking_number)
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-[var(--color-steel)]">Tracking #: {{ $order->tracking_number }}</span>
                            </div>
                        @endif
                    </div>
                    <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded text-sm font-medium hover:bg-[var(--color-red-dark)] transition">Save Shipping Details</button>
                </form>
            </div>
            @endif

            {{-- Order Items --}}
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-lg text-[var(--color-ink)] mb-4">Order Items ({{ $order->items->sum('quantity') }} items)</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b hairline text-left">
                                <th class="pb-3 font-semibold">Product</th>
                                <th class="pb-3 font-semibold">SKU</th>
                                <th class="pb-3 font-semibold text-center">Qty</th>
                                <th class="pb-3 font-semibold text-right">Price</th>
                                <th class="pb-3 font-semibold text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-paper-dim)]">
                            @foreach($order->items ?? [] as $item)
                                <tr class="py-2">
                                    <td class="py-3">
                                        <p class="font-medium text-[var(--color-ink)]">{{ $item->product->name ?? ($item->product_name ?? 'Product') }}</p>
                                        @if($item->variant ?? null)
                                            <p class="text-xs text-[var(--color-steel)]">Variant: {{ $item->variant->name }}</p>
                                        @endif
                                    </td>
                                    <td class="py-3 text-[var(--color-steel)]">{{ $item->sku ?? 'N/A' }}</td>
                                    <td class="py-3 text-center">{{ $item->quantity }}</td>
                                    <td class="py-3 text-right">{{ format_currency($item->unit_price) }}</td>
                                    <td class="py-3 text-right font-medium">{{ format_currency($item->line_total ?? ($item->quantity * $item->unit_price)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t hairline">
                            <tr class="py-2">
                                <td colspan="4" class="pt-3 text-right text-[var(--color-steel)]">Subtotal</td>
                                <td class="pt-3 text-right">{{ format_currency($order->subtotal ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="py-1 text-right text-[var(--color-steel)]">Shipping</td>
                                <td class="py-1 text-right">{{ format_currency($order->shipping_cost ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="py-1 text-right text-[var(--color-steel)]">Tax</td>
                                <td class="py-1 text-right">{{ format_currency($order->tax ?? 0) }}</td>
                            </tr>
                            @if(($order->discount ?? 0) > 0)
                                <tr>
                                    <td colspan="4" class="py-1 text-right text-green-600">Discount{{ $order->coupon_code ? ' (' . $order->coupon_code . ')' : '' }}</td>
                                    <td class="py-1 text-right text-green-600">-{{ format_currency($order->discount) }}</td>
                                </tr>
                            @endif
                            @if(($order->additional_charges ?? 0) > 0)
                                <tr>
                                    <td colspan="4" class="py-1 text-right text-[var(--color-ink)]">Additional Charges</td>
                                    <td class="py-1 text-right">{{ format_currency($order->additional_charges) }}</td>
                                </tr>
                            @endif
                            <tr class="border-t hairline">
                                <td colspan="4" class="pt-3 text-right font-bold text-lg">Grand Total</td>
                                <td class="pt-3 text-right font-bold text-lg">{{ format_currency($order->grand_total ?? 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if($order->statusHistories->count() > 0)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Status History</h2>
                <div class="space-y-3">
                    @foreach($order->statusHistories as $history)
                        <div class="flex items-start gap-3 text-sm">
                            <div class="w-2 h-2 bg-[var(--color-red)] rounded-full mt-1.5 flex-shrink-0"></div>
                            <div class="flex-1">
                                <p class="font-medium text-[var(--color-ink)]">
                                    @if($history->from_status)
                                        {{ ucfirst(str_replace('_', ' ', $history->from_status)) }} &rarr; {{ ucfirst(str_replace('_', ' ', $history->to_status)) }}
                                    @else
                                        {{ ucfirst(str_replace('_', ' ', $history->to_status)) }}
                                    @endif
                                </p>
                                <p class="text-xs text-[var(--color-steel)]">{{ $history->created_at }}</p>
                                @if($history->note)
                                    <p class="text-sm text-[var(--color-steel)] mt-1">{{ $history->note }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif



            @if($order->returns->count() > 0)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Returns</h2>
                <div class="space-y-3">
                    @foreach($order->returns as $return)
                        <div class="flex items-center justify-between p-3 border hairline rounded">
                            <div>
                                <p class="text-sm font-medium text-[var(--color-ink)]">Return #{{ $return->return_number }}</p>
                                <p class="text-xs text-[var(--color-steel)]">Status: {{ $return->status?->label() ?? 'Unknown' }}</p>
                            </div>
                            <a href="{{ route('admin.returns.show', $return->getKey()) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">View</a>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if($order->notes ?? null)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-3">Notes</h2>
                <p class="text-sm text-[var(--color-steel)]">{{ $order->notes }}</p>
            </div>
            @endif
        </div>
    </div>

    @include('components.admin.verify-order-modal')
</div>
@endsection