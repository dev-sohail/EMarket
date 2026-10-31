@extends('layouts.store')

@section('title', 'Affiliate Program')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10">

    {{-- Hero --}}
    <div class="bg-white rounded-lg border overflow-hidden mb-8">
        <div class="p-10 text-center">
            <i class="fa-solid fa-user-group text-4xl text-[var(--color-red)] mb-3"></i>
            <h1 class="text-3xl font-bold text-[var(--color-ink)] mb-2">Become an Affiliate Partner</h1>
            <p class="text-sm text-[var(--color-steel)] max-w-2xl mx-auto mb-6">Share your unique referral link, and earn a <span class="font-semibold text-[var(--color-ink)]">{{ number_format(affiliate_commission_rate(), 2) }}%</span> commission on every order placed through it. It's free to join — apply, get approved, and start earning.</p>

            @if($state === 'disabled')
                <div class="max-w-xl mx-auto p-4 rounded-lg border border-[var(--color-red)]/30 bg-[var(--color-red)]/10 text-[var(--color-red)] text-sm">We are not accepting new affiliate applications right now. Please check back later.</div>
            @elseif($state === 'guest')
                <div class="flex items-center justify-center gap-3 flex-wrap">
                    <a href="{{ route('login') }}" class="border border-[var(--color-ink)] text-[var(--color-ink)] px-6 py-2.5 rounded-lg font-medium hover:bg-[var(--color-paper-dim)] transition">Login</a>
                    <a href="{{ route('register') }}" class="bg-[var(--color-red)] text-white px-6 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Create Account</a>
                </div>
                <p class="text-xs text-[var(--color-steel)] mt-3">You need an account to apply. Already registered? Login and apply from this page.</p>
            @elseif($state === 'eligible')
                <form method="POST" action="{{ route('customer.affiliate.apply') }}">
                    @csrf
                    <button type="submit" class="bg-[var(--color-red)] text-white px-6 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Apply Now</button>
                </form>
            @elseif($state === 'pending')
                <div class="max-w-xl mx-auto p-4 rounded-lg border border-[#1B7A43]/30 bg-[#1B7A43]/10 text-[#1B7A43] text-sm">Your affiliate application is pending approval. We will activate your referral link soon.</div>
                <a href="{{ route('customer.affiliate') }}" class="inline-block mt-4 text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm font-medium">View Application Status</a>
            @elseif($state === 'suspended')
                <div class="max-w-xl mx-auto p-4 rounded-lg border border-[var(--color-red)]/30 bg-[var(--color-red)]/10 text-[var(--color-red)] text-sm">Your affiliate account is currently suspended. Contact support if you believe this is a mistake.</div>
            @else
                <div class="max-w-xl mx-auto p-4 rounded-lg border border-[#1B7A43]/30 bg-[#1B7A43]/10 text-[#1B7A43] text-sm">You are an approved affiliate partner — start sharing your link and earning!</div>
                <a href="{{ route('customer.affiliate') }}" class="inline-block mt-4 bg-[var(--color-red)] text-white px-6 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Go to My Affiliate Dashboard</a>
            @endif
        </div>
    </div>

    {{-- Program details --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
        <div class="bg-white rounded-lg border p-6 text-center">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Commission</p>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ number_format(affiliate_commission_rate(), 2) }}%</p>
            <p class="text-xs text-[var(--color-steel)] mt-1">of each referred order</p>
        </div>
        <div class="bg-white rounded-lg border p-6 text-center">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Attribution Window</p>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ affiliate_cookie_days() }} days</p>
            <p class="text-xs text-[var(--color-steel)] mt-1">after a visitor clicks your link</p>
        </div>
        <div class="bg-white rounded-lg border p-6 text-center">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Minimum Payout</p>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">{{ format_currency(affiliate_min_payout()) }}</p>
            <p class="text-xs text-[var(--color-steel)] mt-1">before payouts are issued</p>
        </div>
        <div class="bg-white rounded-lg border p-6 text-center">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase">Getting Started</p>
            <p class="text-2xl font-bold text-[var(--color-ink)] mt-1">Free</p>
            <p class="text-xs text-[var(--color-steel)] mt-1">to apply — no fees at all</p>
        </div>
    </div>

    {{-- How it works --}}
    <h2 class="text-xl font-bold text-[var(--color-ink)] mb-4">How It Works</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
        <div class="bg-white rounded-lg border p-6 text-center">
            <i class="fa-solid fa-user-plus text-2xl text-[var(--color-red)] mb-2"></i>
            <h3 class="font-semibold text-[var(--color-ink)] text-sm mb-1">1. Create an account & apply</h3>
            <p class="text-xs text-[var(--color-steel)]">Register or login, then submit your affiliate application. Our team reviews and approves it.</p>
        </div>
        <div class="bg-white rounded-lg border p-6 text-center">
            <i class="fa-solid fa-link text-2xl text-[var(--color-red)] mb-2"></i>
            <h3 class="font-semibold text-[var(--color-ink)] text-sm mb-1">2. Get your referral link</h3>
            <p class="text-xs text-[var(--color-steel)]">Once approved, you get a unique link like <code class="text-[10px] bg-[var(--color-paper-dim)] px-1 rounded">{{ url('/ref/YOURCODE') }}</code> to share anywhere.</p>
        </div>
        <div class="bg-white rounded-lg border p-6 text-center">
            <i class="fa-solid fa-sack-dollar text-2xl text-[var(--color-red)] mb-2"></i>
            <h3 class="font-semibold text-[var(--color-ink)] text-sm mb-1">3. Earn commission</h3>
            <p class="text-xs text-[var(--color-steel)]">Every order placed through your link earns you a commission, tracked in your dashboard.</p>
        </div>
    </div>

    {{-- Terms --}}
    <div class="bg-white rounded-lg border p-8">
        <h2 class="text-xl font-bold text-[var(--color-ink)] mb-4">Affiliate Program Terms &amp; Conditions</h2>
        <ol class="space-y-3 text-sm text-[var(--color-ink-soft)] list-decimal list-inside">
            <li>You must have a registered account to participate. Applications are reviewed and approved by our team before your referral link is issued.</li>
            <li>When someone clicks your referral link, they are attributed to you via a browser cookie for {{ affiliate_cookie_days() }} days. Orders placed within that window are credited to you.</li>
            <li>Commission is calculated at {{ number_format(affiliate_commission_rate(), 2) }}% of the total value of each attributed order.</li>
            <li>Self-referrals — orders placed by you or through your own account — are not eligible for commission.</li>
            <li>Commissions are recorded as <span class="font-medium">Pending</span> and become eligible for payment after review and approval by our team.</li>
            <li>Payouts are issued once your available balance reaches the minimum payout of {{ format_currency(affiliate_min_payout()) }}. Payment references are recorded with each payout.</li>
            <li>Duplicate, fraudulent, cancelled or refunded orders may be rejected and will not earn commission.</li>
            <li>We reserve the right to suspend or remove affiliates who violate these terms, spam, or otherwise abuse the program.</li>
            <li>These terms may be updated from time to time. Continued participation in the program after changes constitutes acceptance of the updated terms.</li>
        </ol>
    </div>
</div>
@endsection