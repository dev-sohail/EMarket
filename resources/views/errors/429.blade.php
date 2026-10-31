@extends('errors.partials.shell')

@section('status', '429')
@section('title', 'Too many requests')
@section('path', $path ?? request()->path())

@section('lead', 'This endpoint limits how often it can be called, and the limit was reached. This is deliberate, to stop automated abuse.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
    @if(filled($retryAfter ?? null))
        <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
            <dt class="text-[var(--color-steel)] shrink-0">Retry after</dt>
            <dd class="text-xs font-medium text-[var(--color-ink)] text-right">
                {{ $retryAfter }} second{{ $retryAfter === 1 ? '' : 's' }}
            </dd>
        </div>
    @endif
@endsection

@section('hint')
    <p>
        Sign-in and checkout are limited to a handful of attempts per minute.
        @if(filled($retryAfter ?? null))
            Wait about {{ $retryAfter }} second{{ $retryAfter === 1 ? '' : 's' }} and try again.
        @else
            Wait a moment and try again.
        @endif
        Repeated retries reset the window and lock you out for longer, so retrying immediately makes it worse.
    </p>
@endsection

@section('actions')
    <button type="button" data-action="reload" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
        <i class="fa-solid fa-rotate-right mr-2"></i>Try again
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