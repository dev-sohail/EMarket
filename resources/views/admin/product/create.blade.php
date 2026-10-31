@extends('layouts.admin')

@section('title', 'Create Product')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <nav class="flex items-center gap-2 text-xs text-[var(--color-steel)] mb-1">
            <a href="{{ route('admin.products.index') }}" class="hover:text-[var(--color-ink)] transition">Products</a>
            <i class="fa-solid fa-chevron-right text-[0.6rem]"></i>
            <span class="text-[var(--color-ink-soft)]">New</span>
        </nav>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create Product</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Name, price and stock are the minimum needed to list a product.</p>
    </div>
    <a href="{{ route('admin.products.index') }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
        Back to Products
    </a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" id="product-form">
    @csrf

    @include('admin.product.partials.form', ['product' => null])

    {{-- Sticky so the save action is reachable without scrolling past the
         whole sidebar on a long form. --}}
    <div class="sticky bottom-0 mt-6 bg-[var(--color-paper)]/95 backdrop-blur border-t hairline py-3 flex items-center justify-end gap-3">
        <a href="{{ route('admin.products.index') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
        <button type="submit" class="px-6 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-semibold hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-plus mr-1"></i> Create Product
        </button>
    </div>
</form>
@endsection

@push('scripts')
@include('admin.product.partials.scripts')
@endpush