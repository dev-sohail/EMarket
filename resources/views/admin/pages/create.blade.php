@extends('layouts.admin')

@section('title', 'Create Page')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <nav class="flex items-center gap-2 text-xs text-[var(--color-steel)] mb-1">
            <a href="{{ route('admin.pages.index') }}" class="hover:text-[var(--color-ink)] transition">Pages</a>
            <i class="fa-solid fa-chevron-right text-[0.6rem]"></i>
            <span class="text-[var(--color-ink-soft)]">New</span>
        </nav>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create Page</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">The slug is generated from the title &mdash; edit it only if you need a specific URL.</p>
    </div>
    <a href="{{ route('admin.pages.index') }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
        Back to Pages
    </a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.pages.store') }}">
    @csrf

    @include('admin.pages.partials.form', ['page' => null])

    <div class="sticky bottom-0 mt-6 bg-[var(--color-paper)]/95 backdrop-blur border-t hairline py-3 flex items-center justify-end gap-3">
        <a href="{{ route('admin.pages.index') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
        <button type="submit" class="px-6 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-semibold hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-plus mr-1"></i> Create Page
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>
<script nonce="{{ $cspNonce }}">
    var editor = window.ckeditorSync(CKEDITOR.replace('content-editor', {
        height: 420,
        removePlugins: 'elementspath',
        colorButton: true
    }));

    // Mirror the "auto-generated from the title" promise in the field hint:
    // the slug column is NOT NULL and the store request requires it, so a
    // blank slug has to be filled in the browser before submission.
    (function () {
        const title = document.getElementById('title');
        const slug = document.querySelector('[data-slug-input]');
        if (!title || !slug) return;

        // Once the slug has been touched by hand it belongs to the user and
        // the title stops driving it.
        let edited = slug.value.trim() !== '';
        slug.addEventListener('input', function () { edited = true; });

        title.addEventListener('input', function () {
            if (edited) return;

            slug.value = title.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });
    })();
</script>
@endpush
