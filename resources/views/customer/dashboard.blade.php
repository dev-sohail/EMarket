@extends('layouts.store')

@section('title', 'My Dashboard')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">My Dashboard</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Welcome Card --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-[var(--color-line)] p-6 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-[var(--color-red)]/10 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-user text-3xl text-[var(--color-red)]"></i>
                </div>
                <div>
                    <p class="text-[var(--color-steel)]/80 text-sm">Welcome back,</p>
                    <p class="text-2xl font-bold text-[var(--color-ink)]">{{ auth()->user()->name }}</p>
                    <p class="text-[var(--color-steel)]/70 text-sm mt-1">Here's what's happening with your account today.</p>
                </div>
            </div>
        </div>

        {{-- Total Spent --}}
        <div class="bg-white rounded-xl border border-[var(--color-line)] p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-[var(--color-steel)]">Total Spent</p>
                    <p class="text-3xl font-bold text-[var(--color-ink)] mt-2">{{ format_currency($stats['total_spent']) }}</p>
                    <p class="text-xs text-[var(--color-steel)] mt-1">Lifetime purchases</p>
                </div>
                <div class="w-12 h-12 bg-[#1B7A43]/10 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-dollar-sign text-xl text-[#1B7A43]"></i>
                </div>
            </div>
        </div>

        {{-- Pending Orders --}}
        <div class="bg-white rounded-xl border border-[var(--color-line)] p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-[var(--color-steel)]">Pending Orders</p>
                    <p class="text-3xl font-bold text-[var(--color-ink)] mt-2">{{ $stats['pending_orders'] }}</p>
                    <p class="text-xs text-[var(--color-steel)] mt-1">Currently processing</p>
                </div>
                <div class="w-12 h-12 bg-[var(--color-ink)]/10 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-clock text-xl text-[var(--color-ink)]"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Secondary Stats Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
        {{-- Total Orders --}}
        <div class="bg-white rounded-xl border border-[var(--color-line)] p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-[#1B7A43]/10 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-file-lines text-2xl text-[#1B7A43]"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[var(--color-ink)]">{{ $stats['total_orders'] }}</p>
                    <p class="text-sm text-[var(--color-steel)]">Total Orders</p>
                </div>
            </div>
        </div>

        {{-- Wishlist --}}
        <div class="bg-white rounded-xl border border-[var(--color-line)] p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-[var(--color-red)]/10 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-heart text-2xl text-[var(--color-red)]"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[var(--color-ink)]">{{ $customer->wishlist?->items()->count() ?? 0 }}</p>
                    <p class="text-sm text-[var(--color-steel)]">Wishlist Items</p>
                </div>
            </div>
        </div>

        {{-- Reviews --}}
        <div class="bg-white rounded-xl border border-[var(--color-line)] p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-[var(--color-ink)]/10 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-star text-2xl text-[var(--color-ink)]"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[var(--color-ink)]">{{ $customer->reviews()->count() }}</p>
                    <p class="text-sm text-[var(--color-steel)]">My Reviews</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Orders --}}
    <div class="bg-white rounded-xl border border-[var(--color-line)] shadow-sm overflow-hidden mb-8">
        <div class="p-6 border-b border-[var(--color-line)] flex items-center justify-between">
            <h2 class="text-xl font-semibold text-[var(--color-ink)]">Recent Orders</h2>
            <a href="{{ route('customer.orders') }}" class="inline-flex items-center gap-1 text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium transition-colors">
                View All
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[var(--color-paper-dim)]">
                    <tr>
                        <th class="text-left px-6 py-4 text-xs font-semibold text-[var(--color-steel)] uppercase tracking-wider">Order #</th>
                        <th class="text-left px-6 py-4 text-xs font-semibold text-[var(--color-steel)] uppercase tracking-wider">Date</th>
                        <th class="text-left px-6 py-4 text-xs font-semibold text-[var(--color-steel)] uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-4 text-xs font-semibold text-[var(--color-steel)] uppercase tracking-wider">Total</th>
                        <th class="text-right px-6 py-4 text-xs font-semibold text-[var(--color-steel)] uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-line)]">
                    @forelse($recentOrders as $order)
                        <tr class="hover:bg-[var(--color-paper-dim)]/50 transition-colors">
                            <td class="px-6 py-4 text-sm font-semibold text-[var(--color-ink)]">#{{ $order->order_number }}</td>
                            <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $order->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                                    {{ $order->status?->value === 'delivered' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : '' }}
                                    {{ $order->status?->value === 'processing' ? 'bg-[var(--color-paper-dim)] text-[var(--color-red-dark)]' : '' }}
                                    {{ $order->status?->value === 'shipped' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : '' }}
                                    {{ $order->status?->value === 'pending' ? 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' : '' }}
                                    {{ $order->status?->value === 'cancelled' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : '' }}">
                                    {{ $order->status?->label() ?? 'Unknown' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-[var(--color-ink)]">{{ format_currency($order->grand_total) }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('customer.orders.show', $order->getKey()) }}" class="inline-flex items-center gap-1 text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium transition-colors">
                                    View
                                    <i class="fa-solid fa-arrow-right text-xs"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <i class="fa-solid fa-box-open text-4xl text-[var(--color-steel)]/50"></i>
                                    <p class="text-[var(--color-steel)]">No orders yet. Start shopping to see your orders here!</p>
                                    <a href="{{ route('shop') }}" class="mt-2 px-4 py-2 bg-[var(--color-red)] text-white rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition-colors">
                                        Browse Products
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold text-[var(--color-ink)] mb-6">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <a href="{{ route('customer.profile') }}" class="group bg-white rounded-xl border border-[var(--color-line)] p-6 text-center hover:border-[var(--color-red)] hover:shadow-md transition-all">
                <div class="w-14 h-14 bg-[var(--color-red)]/10 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-user text-2xl text-[var(--color-red)]"></i>
                </div>
                <span class="block text-sm font-medium text-[var(--color-ink)]">Edit Profile</span>
            </a>
            <a href="{{ route('customer.addresses') }}" class="group bg-white rounded-xl border border-[var(--color-line)] p-6 text-center hover:border-[var(--color-red)] hover:shadow-md transition-all">
                <div class="w-14 h-14 bg-[var(--color-red)]/10 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-location-dot text-2xl text-[var(--color-red)]"></i>
                </div>
                <span class="block text-sm font-medium text-[var(--color-ink)]">Addresses</span>
            </a>
            <a href="{{ route('customer.wishlist') }}" class="group bg-white rounded-xl border border-[var(--color-line)] p-6 text-center hover:border-[var(--color-red)] hover:shadow-md transition-all">
                <div class="w-14 h-14 bg-[var(--color-red)]/10 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-heart text-2xl text-[var(--color-red)]"></i>
                </div>
                <span class="block text-sm font-medium text-[var(--color-ink)]">Wishlist</span>
            </a>
            <a href="{{ route('customer.reviews') }}" class="group bg-white rounded-xl border border-[var(--color-line)] p-6 text-center hover:border-[var(--color-red)] hover:shadow-md transition-all">
                <div class="w-14 h-14 bg-[var(--color-red)]/10 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-star text-2xl text-[var(--color-red)]"></i>
                </div>
                <span class="block text-sm font-medium text-[var(--color-ink)]">Reviews</span>
            </a>
            <a href="{{ route('customer.affiliate') }}" class="group bg-white rounded-xl border border-[var(--color-line)] p-6 text-center hover:border-[var(--color-red)] hover:shadow-md transition-all lg:col-start-3 sm:col-start-2">
                <div class="w-14 h-14 bg-[var(--color-red)]/10 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-user-group text-2xl text-[var(--color-red)]"></i>
                </div>
                <span class="block text-sm font-medium text-[var(--color-ink)]">Affiliate</span>
            </a>
        </div>
    </div>
</div>
@endsection