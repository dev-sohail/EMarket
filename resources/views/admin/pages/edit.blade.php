@extends('layouts.admin')

@section('title', 'Edit Page')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <nav class="flex items-center gap-2 text-xs text-[var(--color-steel)] mb-1">
            <a href="{{ route('admin.pages.index') }}" class="hover:text-[var(--color-ink)] transition">Pages</a>
            <i class="fa-solid fa-chevron-right text-[0.6rem]"></i>
            <span class="text-[var(--color-ink-soft)] max-w-[16rem] truncate">{{ $page->title }}</span>
        </nav>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Edit Page</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">
            Currently {{ $page->status === 'published' ? 'live on the storefront' : 'a hidden draft' }} &middot; last updated {{ $page->updated_at?->diffForHumans() }}.
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('page.show', $page->slug) }}" target="_blank" rel="noopener" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition whitespace-nowrap">
            <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i> View
        </a>
        <a href="{{ route('admin.pages.index') }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition whitespace-nowrap">
            Back to Pages
        </a>
    </div>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.pages.update', $page) }}">
    @csrf
    @method('PUT')

    @include('admin.pages.partials.form', ['page' => $page])

    <div class="sticky bottom-0 mt-6 bg-[var(--color-paper)]/95 backdrop-blur border-t hairline py-3 flex items-center justify-between gap-3">
        <p class="text-xs text-[var(--color-steel)] hidden sm:block">Changes go live as soon as you save.</p>
        <div class="flex items-center gap-3 ml-auto">
            <a href="{{ route('admin.pages.index') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-semibold hover:bg-[var(--color-red-dark)] transition">
                <i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>
<script nonce="{{ $cspNonce }}">
    window.ckeditorSync(CKEDITOR.replace('content-editor', {
        height: 420,
        removePlugins: 'elementspath',
        colorButton: true
    }));
</script>
@endpush
