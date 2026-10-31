@extends('layouts.admin')
@section('title', 'Customer Details')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Customer: {{ $customer->first_name ?? '' }} {{ $customer->last_name ?? '' }}</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.customers.edit', $customer->getKey()) }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm border px-3 py-1.5 rounded-lg">Edit</a>
        <a href="{{ route('admin.customers.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Customers</a>
    </div>
</div>
@endsection
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="space-y-6">
        {{-- Profile Card --}}
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-16 h-16 bg-[var(--color-paper-dim)] rounded-full flex items-center justify-center text-[var(--color-red)] font-bold text-xl">{{ substr($customer->first_name ?? 'U', 0, 1) }}</div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold text-[var(--color-ink)]">{{ $customer->first_name ?? '' }} {{ $customer->last_name ?? '' }}</h2>
                        @if(!$customer->user_id)
                            <span class="inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full bg-[var(--color-paper-dim)] text-[var(--color-steel)]">Guest</span>
                        @endif
                    </div>
                    <p class="text-sm text-[var(--color-steel)]">{{ $customer->email ?? '' }}</p>
                </div>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-[var(--color-steel)]">Phone</span><span>{{ $customer->phone ?? '-' }}</span></div>
                <div class="flex justify-between"><span class="text-[var(--color-steel)]">Joined</span><span>{{ $customer->created_at->format('M d, Y') }}</span></div>
                <div class="flex justify-between"><span class="text-[var(--color-steel)]">Total Orders</span><span class="font-medium">{{ $customer->orders_count ?? 0 }}</span></div>
                <div class="flex justify-between"><span class="text-[var(--color-steel)]">Total Spent</span><span class="font-medium">{{ format_currency($totalSpent ?? 0) }}</span></div>
                <div class="flex justify-between"><span class="text-[var(--color-steel)]">Status</span><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $customer->is_active ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></div>
            </div>
        </div>

        {{-- Account Actions --}}
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h3 class="font-semibold text-[var(--color-ink)] mb-3">Portal Account</h3>
            @if($customer->user_id)
                <div class="space-y-3">
                    <p class="text-sm text-[var(--color-steel)]">This customer has a portal login account.</p>
                    <form method="POST" action="{{ route('admin.customers.impersonate', $customer->getKey()) }}">
                        @csrf
                        <button type="submit" class="w-full bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)]">Impersonate Customer</button>
                    </form>
                    <p class="text-xs text-[var(--color-steel)]">You will be logged in as this customer on the storefront.</p>
                </div>
            @else
                <div class="space-y-3">
                    <p class="text-sm text-[var(--color-steel)]">This customer does not have a portal login account yet. This is a guest checkout customer.</p>
                    <form method="POST" action="{{ route('admin.customers.user-account', $customer->getKey()) }}" class="space-y-3">
                        @csrf
                        <div>
                            <input type="text" name="password" placeholder="Temp password (min 8 chars)" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" required minlength="8">
                        </div>
                        <div>
                            <input type="text" name="password_confirmation" placeholder="Confirm password" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" required minlength="8">
                        </div>
                        <button type="submit" class="w-full bg-[#1B7A43] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#1B7A43]">Create Login Account</button>
                    </form>
                    <p class="text-xs text-[var(--color-steel)]">This creates a storefront login for the customer's email.</p>
                </div>
            @endif
        </div>

        {{-- Addresses --}}
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h3 class="font-semibold text-[var(--color-ink)] mb-3">Addresses ({{ $customer->addresses->count() }})</h3>
            @forelse($customer->addresses as $address)
                <div class="border rounded-lg p-3 mb-2 text-sm">
                    <p class="font-medium">{{ $address->first_name ?? '' }} {{ $address->last_name ?? '' }}</p>
                    <p class="text-[var(--color-steel)]">{{ $address->address_line_1 ?? '' }}</p>
                    @if($address->address_line_2)
                        <p class="text-[var(--color-steel)]">{{ $address->address_line_2 }}</p>
                    @endif
                    <p class="text-[var(--color-steel)]">{{ $address->city ?? '' }}, {{ $address->state ?? '' }} {{ $address->postal_code ?? '' }}</p>
                    @if($address->phone)
                        <p class="text-[var(--color-steel)]">{{ $address->phone }}</p>
                    @endif
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)]">No addresses on file.</p>
            @endforelse
        </div>
    </div>

    <div class="lg:col-span-2 space-y-6">
        {{-- Order History --}}
        <div class="bg-[var(--color-paper)] rounded-lg border">
            <div class="p-6 border-b"><h2 class="font-semibold text-[var(--color-ink)]">Order History</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[var(--color-paper-dim)]"><tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Order</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Total</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Date</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y">
                        @forelse($customer->orders ?? [] as $order)
                            <tr class="hover:bg-[var(--color-paper-dim)]">
                                <td class="px-6 py-4 text-sm font-medium">#{{ $order->order_number }}</td>
                                <td class="px-6 py-4"><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $order->status?->value === 'delivered' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : ($order->status?->value === 'processing' ? 'bg-[var(--color-paper-dim)] text-[var(--color-red)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]') }}">{{ $order->status?->label() ?? 'Unknown' }}</span></td>
                                <td class="px-6 py-4 text-sm font-medium">{{ format_currency($order->grand_total) }}</td>
                                <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $order->created_at->format('M d, Y') }}</td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('admin.orders.show', $order->getKey()) }}" class="text-[var(--color-red)] text-sm">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-[var(--color-steel)]">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Wishlist --}}
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h3 class="font-semibold text-[var(--color-ink)] mb-3">Wishlist ({{ $customer->wishlist ? $customer->wishlist->items->count() : 0 }})</h3>
            @if($customer->wishlist && $customer->wishlist->items->count() > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($customer->wishlist->items as $item)
                        <div class="border rounded-lg p-3 text-sm">
                            <p class="font-medium">{{ $item->product->name ?? 'Product' }}</p>
                            <p class="text-[var(--color-steel)]">{{ format_currency($item->product->base_price ?? 0) }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-[var(--color-steel)]">No wishlist items.</p>
            @endif
        </div>

        {{-- Reviews --}}
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h3 class="font-semibold text-[var(--color-ink)] mb-3">Reviews ({{ $customer->reviews->count() }})</h3>
            @forelse($customer->reviews as $review)
                <div class="border rounded-lg p-3 mb-2 text-sm">
                    <div class="flex items-center justify-between">
                        <p class="font-medium">{{ $review->product->name ?? 'Product' }}</p>
                        <span class="text-[var(--color-ink-soft)]">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                    </div>
                    <p class="text-[var(--color-steel)] mt-1">{{ $review->comment ?? '-' }}</p>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)]">No reviews submitted.</p>
            @endforelse
        </div>

        {{-- Returns --}}
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h3 class="font-semibold text-[var(--color-ink)] mb-3">Returns ({{ $customer->returns->count() }})</h3>
            @forelse($customer->returns as $return)
                <div class="border rounded-lg p-3 mb-2 text-sm">
                    <div class="flex items-center justify-between">
                        <p class="font-medium">Return #{{ $return->getKey() }}</p>
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $return->status?->value === 'approved' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : ($return->status?->value === 'rejected' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]') }}">{{ $return->status?->label() ?? 'Unknown' }}</span>
                    </div>
                    <p class="text-[var(--color-steel)] mt-1">Order #{{ $return->order->order_number ?? '-' }}</p>
                </div>
            @empty
                <p class="text-sm text-[var(--color-steel)]">No return requests.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
