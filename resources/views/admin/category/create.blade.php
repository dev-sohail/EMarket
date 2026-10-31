@extends('layouts.admin')

@section('title', 'Create Category')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <nav class="flex items-center gap-2 text-xs text-[var(--color-steel)] mb-1">
            <a href="{{ route('admin.categories.index') }}" class="hover:text-[var(--color-ink)] transition">Categories</a>
            <i class="fa-solid fa-chevron-right text-[0.6rem]"></i>
            <span class="text-[var(--color-ink-soft)]">New</span>
        </nav>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create Category</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Only the name is required &mdash; the slug is generated from it.</p>
    </div>
    <a href="{{ route('admin.categories.index') }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
        Back to Categories
    </a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data">
    @csrf

    @include('admin.category.partials.form', ['category' => null])

    <div class="sticky bottom-0 mt-6 bg-[var(--color-paper)]/95 backdrop-blur border-t hairline py-3 flex items-center justify-end gap-3">
        <a href="{{ route('admin.categories.index') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
        <button type="submit" class="px-6 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-semibold hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-plus mr-1"></i> Create Category
        </button>
    </div>
</form>
@endsection

@push('scripts')
@include('admin.category.partials.scripts')
@endpush