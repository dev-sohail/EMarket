@extends('errors.partials.shell')

@section('status', '503')
@section('title', 'Temporarily unavailable')
@section('path', $path ?? request()->path())

@section('lead', 'The store is offline for maintenance or is not ready to serve traffic yet. Nothing was lost.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
@endsection

@section('hint')
    <p>
        This is expected during a deploy. There is nothing to fix on your side &mdash; come back in a few minutes.
        Any basket you had will still be there.
    </p>
@endsection

@section('actions')
    <button type="button" data-action="reload" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
        <i class="fa-solid fa-rotate-right mr-2"></i>Reload
    </button>
    @if(request()->is('admin', 'admin/*'))
        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            <i class="fa-solid fa-gauge-high mr-2"></i>Dashboard
        </a>
    @endif
@endsection