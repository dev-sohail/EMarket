@extends('errors.partials.shell')

@section('status', '401')
@section('title', 'Please sign in')
@section('path', $path ?? request()->path())

@section('lead', 'This page needs a signed-in account. Yours has ended or was never established for this area.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
@endsection

@section('hint')
    @if(request()->is('admin', 'admin/*'))
        <p>Back-office sessions are separate from customer accounts, so signing in to the shop will not grant access here.</p>
    @else
        <p>You can carry on as a guest &mdash; only account and order pages need a sign-in.</p>
    @endif
@endsection

@section('actions')
    @if(request()->is('admin', 'admin/*'))
        <a href="{{ route('admin.login') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-right-to-bracket mr-2"></i>Sign in to the back office
        </a>
        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            <i class="fa-solid fa-store mr-2"></i>Storefront
        </a>
    @else
        <a href="{{ route('login') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-right-to-bracket mr-2"></i>Sign in
        </a>
        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            <i class="fa-solid fa-house mr-2"></i>Back to the shop
        </a>
    @endif
@endsection