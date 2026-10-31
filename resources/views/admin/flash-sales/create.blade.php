@extends('layouts.admin')
@section('title', 'Create Flash Sale')
@section('header')
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create Flash Sale</h1>
    <a href="{{ route('admin.flash-sales.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Flash Sales</a>
</div>
@endsection
@section('content')
<form method="POST" action="{{ route('admin.flash-sales.store') }}">
    @csrf
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Flash Sale Information</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('name') border-[var(--color-red)] @enderror">
                        @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Default Flash Price ({{ currency_symbol() }})</label>
                        <input type="number" id="default-flash-price" name="sale_price" value="{{ old('sale_price') }}" step="0.01" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('sale_price') border-[var(--color-red)] @enderror">
                        <p class="text-xs text-[var(--color-steel)] mt-1">Applied to all selected products unless overridden below.</p>
                        @error('sale_price') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 mt-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Select Categories</h2>
                <p class="text-xs text-[var(--color-steel)] mb-3">All products in the selected categories will be included in the sale.</p>
                <div class="max-h-40 overflow-y-auto space-y-2">
                    @foreach($categories as $category)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="category_ids[]" value="{{ $category->getKey() }}" {{ in_array($category->getKey(), old('category_ids', [])) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                            <span class="text-sm text-[var(--color-ink-soft)]">{{ $category->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 mt-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Select Products</h2>
                <p class="text-xs text-[var(--color-steel)] mb-3">Optionally add individual products on top of the categories above. Set a flash price per product if needed.</p>
                <div class="max-h-60 overflow-y-auto space-y-2">
                    @foreach($products as $product)
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="product_ids[]" value="{{ $product->getKey() }}" {{ in_array($product->getKey(), old('product_ids', [])) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                <span class="text-sm text-[var(--color-ink-soft)]">{{ $product->name }} - {{ format_currency($product->base_price) }}</span>
                            </label>
                            <input type="number" name="product_prices[{{ $product->getKey() }}]" value="{{ old('product_prices.' . $product->getKey()) }}" step="0.01" placeholder="Flash price" class="w-32 border hairline bg-[var(--color-paper)] px-3 py-1.5 text-sm focus:outline-none focus:border-[var(--color-ink)]">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div>
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Schedule & Status</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Starts At *</label>
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('starts_at') border-[var(--color-red)] @enderror">
                        @error('starts_at') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Ends At *</label>
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('ends_at') border-[var(--color-red)] @enderror">
                        @error('ends_at') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', 1) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                            <span class="text-sm text-[var(--color-ink)]">Active</span>
                        </label>
                    </div>
                </div>
            </div>
            <button type="submit" class="w-full bg-[var(--color-red)] text-white py-3 rounded font-semibold hover:bg-[var(--color-red-dark)] transition mt-4">Create Flash Sale</button>
        </div>
    </div>
</form>
@endsection
