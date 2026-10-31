@extends('errors.partials.shell')

@section('status', '500')
@section('title', 'Something went wrong on our side')
@section('path', $path ?? request()->path())

{{--
    Deliberately says nothing about the failure. A 500 in production can carry a
    SQL string, a file path or a host name in its message, and this page is
    shown to whoever hit the URL. The detail stays in the log; the timestamp
    below is the handle to quote when reporting it.

    With APP_DEBUG=true this view is never reached: Laravel renders the Symfony
    trace first, which is the point of debug mode.
--}}
@section('lead', 'The request was not completed. The failure has been logged and nothing was saved.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Occurred</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right">{{ now()->format('Y-m-d H:i:s T') }}</dd>
    </div>
@endsection

@section('hint')
    <p>
        Try once more &mdash; if it was a transient database or gateway failure it may simply succeed.
        If it keeps happening, quote the timestamp above when reporting it.
    </p>
@endsection

@section('actions')
    <button type="button" data-action="reload" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
        <i class="fa-solid fa-rotate-right mr-2"></i>Try again
    </button>
    <button type="button" data-action="history-back" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
        <i class="fa-solid fa-arrow-left mr-2"></i>Go back
    </button>
@endsection