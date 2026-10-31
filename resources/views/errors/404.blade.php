@extends('errors.partials.shell')

@section('status', '404')
@section('title', 'We could not find that page')
@section('path', $path ?? request()->path())

@section('lead', 'The address may be mistyped, or the product, order or page it pointed at may have been removed.')

@section('details')
    <div class="flex items-center justify-between gap-4 px-8 sm:px-10 py-3">
        <dt class="text-[var(--color-steel)] shrink-0">Address</dt>
        <dd class="font-mono text-xs text-[var(--color-ink)] text-right break-all">{{ $path ?? request()->path() }}</dd>
    </div>
@endsection

@section('hint')
    @if(request()->is('admin', 'admin/*'))
        <p>
            This is a back-office screen. If you followed a link from a list, the record behind it was probably deleted &mdash;
            go back and reload the list.
        </p>
    @else
        <p>
            Try searching from the shop, or browse a category from the main menu.
        </p>
    @endif
@endsection

@section('actions')
    @if(request()->is('admin', 'admin/*'))
        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-gauge-high mr-2"></i>Dashboard
        </a>
        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            <i class="fa-solid fa-store mr-2"></i>Storefront
        </a>
    @else
        <a href="{{ route('home') }}" class="px-4 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-house mr-2"></i>Back to the shop
        </a>
    @endif
@endsection