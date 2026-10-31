{{--
    Catch-all for any HTTP status without a bespoke page (400, 402, 406, 409…).
    Without it, Laravel's built-in plain view would be served for those, which
    is the inconsistent result this whole set exists to remove.
--}}
@extends('errors.partials.shell')

@section('status', $status ?? 'Error')
@section('title', $title ?? 'That request could not be completed')
@section('path', $path ?? request()->path())

@section('lead', $lead ?? 'The request was rejected before it could be processed. Nothing was changed.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Status</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)]">{{ $status ?? 'Error' }}</dd>
    </div>
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
@endsection

@section('actions')
    @if(request()->is('admin', 'admin/*'))
        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-gauge-high mr-2"></i>Dashboard
        </a>
    @else
        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-house mr-2"></i>Back to the shop
        </a>
    @endif
@endsection