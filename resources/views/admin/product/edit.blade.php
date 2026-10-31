@extends('layouts.admin')

@section('title', 'Edit Product')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <nav class="flex items-center gap-2 text-xs text-[var(--color-steel)] mb-1">
            <a href="{{ route('admin.products.index') }}" class="hover:text-[var(--color-ink)] transition">Products</a>
            <i class="fa-solid fa-chevron-right text-[0.6rem]"></i>
            <span class="text-[var(--color-ink-soft)] truncate max-w-xs">{{ $product->name }}</span>
        </nav>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Edit Product</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-mono">{{ $product->sku }}</span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full {{ $product->is_active ? 'bg-[#1B7A43]' : 'bg-[var(--color-steel)]' }}"></span>
                {{ $product->is_active ? 'Active' : 'Inactive' }}
            </span>
            <span>{{ $product->stock_status?->label() ?? 'Unknown' }}</span>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.products.images', $product->getKey()) }}" class="px-4 py-2 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
            Images
        </a>
        <a href="{{ route('admin.products.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)] transition">Back to Products</a>
    </div>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.products.update', $product->getKey()) }}" enctype="multipart/form-data" id="product-form">
    @csrf
    @method('PUT')

    @include('admin.product.partials.form', ['product' => $product])

    <div class="sticky bottom-0 mt-6 bg-[var(--color-paper)]/95 backdrop-blur border-t hairline py-3 flex items-center justify-between gap-3">
        <button type="button" data-action="deleteProduct"
                data-url="{{ route('admin.products.destroy', $product->getKey()) }}"
                data-name="{{ $product->name }}"
                class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">
            Delete Product
        </button>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.products.index') }}" class="px-4 py-2.5 rounded-lg border hairline text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-[var(--color-red)] text-white text-sm font-semibold hover:bg-[var(--color-red-dark)] transition">
                <i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes
            </button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
@include('admin.product.partials.scripts')
@endpush