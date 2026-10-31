@extends('errors.partials.shell')

@section('status', '419')
@section('title', 'Your session expired')
@section('path', $path ?? request()->path())

@section('lead', 'The form was open longer than your session lasted, so the security token no longer matched and the submission was rejected. Nothing was changed.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
@endsection

@section('hint')
    <p>Reload the page to get a fresh token, then submit the form again. Anything you had already typed is lost, so copy it before reloading if it was long.</p>
    <p>
        If this keeps happening on the same screen, the session is being dropped between page load and submit &mdash; a server-side
        session store problem, not something you did wrong.
    </p>
@endsection

@section('actions')
    {{-- data-action="reload" is handled by resources/js/error-page.js: an
         external file, so no inline script and no CSP nonce is needed. --}}
    <button type="button" data-action="reload" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
        <i class="fa-solid fa-rotate-right mr-2"></i>Reload the page
    </button>
    @if(request()->is('admin', 'admin/*'))
        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            <i class="fa-solid fa-gauge-high mr-2"></i>Dashboard
        </a>
    @else
        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            <i class="fa-solid fa-house mr-2"></i>Back to the shop
        </a>
    @endif
@endsection