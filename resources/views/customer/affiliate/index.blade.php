@extends('layouts.store')

@section('title', 'My Affiliate')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">My Affiliate</h1>

    @if(session('success'))
        <div class="p-4 mb-6 rounded-lg border border-[#1B7A43]/30 bg-[#1B7A43]/10 text-[#1B7A43] text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-4 mb-6 rounded-lg border border-[var(--color-red)]/30 bg-[var(--color-red)]/10 text-[var(--color-red)] text-sm">{{ session('error') }}</div>
    @endif

    @if(! $programEnabled)
        <div class="bg-white rounded-lg border p-8 text-center">
            <i class="fa-solid fa-user-group text-4xl text-[var(--color-steel)] mb-3"></i>
            <h2 class="text-lg font-semibold text-[var(--color-ink)] mb-1">Affiliate program unavailable</h2>
            <p class="text-sm text-[var(--color-steel)]">The affiliate program is not accepting new participants right now. Please check back later.</p>
        </div>
    @elseif($affiliate && $affiliate->status === 'pending')
        <div class="bg-white rounded-lg border p-8 text-center">
            <i class="fa-solid fa-hourglass-half text-4xl text-[var(--color-red)] mb-3"></i>
            <h2 class="text-lg font-semibold text-[var(--color-ink)] mb-1">Application Pending Approval</h2>
            <p class="text-sm text-[var(--color-steel)]">Thanks for applying! Our team is reviewing your affiliate application. You will get access to your referral link as soon as it is approved.</p>
        </div>
    @elseif($affiliate && $affiliate->status === 'suspended')
        <div class="bg-white rounded-lg border p-8 text-center">
            <i class="fa-solid fa-ban text-4xl text-[var(--color-red)] mb-3"></i>
            <h2 class="text-lg font-semibold text-[var(--color-ink)] mb-1">Affiliate Account Suspended</h2>
            <p class="text-sm text-[var(--color-steel)]">Your affiliate account is currently suspended. Contact support if you believe this is a mistake.</p>
        </div>
    @elseif($affiliate && $affiliate->status === 'active')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg border p-6">
                <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Referral Clicks</p>
                <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ number_format($stats['clicks']) }}</p>
            </div>
            <div class="bg-white rounded-lg border p-6">
                <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Total Earned</p>
                <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ format_currency($stats['total_earned']) }}</p>
            </div>
            <div class="bg-white rounded-lg border p-6">
                <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Total Paid</p>
                <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ format_currency($stats['total_paid']) }}</p>
            </div>
            <div class="bg-white rounded-lg border p-6">
                <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Available Balance</p>
                <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ format_currency($stats['balance']) }}</p>
                <p class="text-xs text-[var(--color-steel)] mt-1">Minimum payout: {{ format_currency(affiliate_min_payout()) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-lg border p-6 mb-8">
            <h2 class="font-semibold text-[var(--color-ink)] mb-2">Your Referral Link</h2>
            <p class="text-sm text-[var(--color-steel)] mb-3">Earn {{ number_format($affiliate->effectiveRate(affiliate_commission_rate()), 2) }}% commission on every order placed through your link. Share it anywhere!</p>
            <div class="flex items-center gap-2 flex-wrap">
                <input type="text" readonly data-action="select-text" value="{{ route('referral.go', $affiliate->code) }}" class="flex-1 min-w-[240px] rounded-lg border-[var(--color-line)] bg-[var(--color-paper-dim)] text-sm px-3 py-2 font-mono">
                <button type="button" data-action="copy-text" data-copy-label="Copy Link" data-copy-text="{{ route('referral.go', $affiliate->code) }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition">Copy Link</button>
            </div>
        </div>

        <div class="bg-white rounded-lg border">
            <div class="p-6 border-b hairline">
                <h2 class="text-lg font-semibold text-[var(--color-ink)]">My Commissions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-[var(--color-paper-dim)]">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Order</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Date</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Commission</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Payment Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-line)]">
                        @forelse($commissions as $commission)
                            <tr class="hover:bg-[var(--color-paper-dim)]">
                                <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">#{{ $commission->order->order_number ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $commission->created_at->format('M d, Y') }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-[var(--color-ink)]">{{ format_currency((float) $commission->amount) }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $commission->status === 'paid' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : ($commission->status === 'rejected' ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink)]') }}">{{ ucfirst($commission->status) }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $commission->reference ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-[var(--color-steel)]">No commissions yet — share your referral link to start earning!</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($commissions, 'links'))
                <div class="p-4 border-t">{{ $commissions->links() }}</div>
            @endif
        </div>
    @else
        <div class="bg-white rounded-lg border p-8 text-center mb-8">
            <i class="fa-solid fa-user-group text-4xl text-[var(--color-red)] mb-3"></i>
            <h2 class="text-lg font-semibold text-[var(--color-ink)] mb-1">Become an Affiliate</h2>
            <p class="text-sm text-[var(--color-steel)] max-w-xl mx-auto mb-4">Share your referral link, and earn a {{ number_format(affiliate_commission_rate(), 2) }}% commission on every order placed through it. Apply now and start earning!</p>
            <form method="POST" action="{{ route('customer.affiliate.apply') }}">
                @csrf
                <button type="submit" class="bg-[var(--color-red)] text-white px-6 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Apply Now</button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg border p-6 text-center">
                <i class="fa-solid fa-link text-2xl text-[var(--color-red)] mb-2"></i>
                <h3 class="font-semibold text-[var(--color-ink)] text-sm mb-1">1. Get your link</h3>
                <p class="text-xs text-[var(--color-steel)]">Once approved, you get a unique referral link to share.</p>
            </div>
            <div class="bg-white rounded-lg border p-6 text-center">
                <i class="fa-solid fa-cart-shopping text-2xl text-[var(--color-red)] mb-2"></i>
                <h3 class="font-semibold text-[var(--color-ink)] text-sm mb-1">2. Friends order</h3>
                <p class="text-xs text-[var(--color-steel)]">Anyone who orders within {{ affiliate_cookie_days() }} days of clicking your link counts.</p>
            </div>
            <div class="bg-white rounded-lg border p-6 text-center">
                <i class="fa-solid fa-sack-dollar text-2xl text-[var(--color-red)] mb-2"></i>
                <h3 class="font-semibold text-[var(--color-ink)] text-sm mb-1">3. Earn commission</h3>
                <p class="text-xs text-[var(--color-steel)]">You earn {{ number_format(affiliate_commission_rate(), 2) }}% of each order, paid out once the balance reaches {{ format_currency(affiliate_min_payout()) }}.</p>
            </div>
        </div>
    @endif
</div>
@endsection