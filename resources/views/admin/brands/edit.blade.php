@extends('layouts.admin')

@section('title', 'Edit Brand')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Edit Brand</h1>
    <a href="{{ route('admin.brands.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Brands</a>
</div>
@endsection

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('admin.brands.update', $brand) }}" enctype="multipart/form-data" class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name *</label>
            <input type="text" name="name" value="{{ old('name', $brand->name) }}" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('name') border-[var(--color-red)] @enderror">
            @error('name') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Slug *</label>
            <input type="text" name="slug" value="{{ old('slug', $brand->slug) }}" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('slug') border-[var(--color-red)] @enderror">
            @error('slug') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Logo</label>
            @if($brand->logo)
                <img src="{{ asset('storage/' . $brand->logo) }}" class="w-20 h-20 object-cover rounded mb-2 border hairline">
            @endif
            <input type="file" name="logo" accept="image/*" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('logo') border-[var(--color-red)] @enderror">
            <p class="text-xs text-[var(--color-steel)] mt-1">Leave empty to keep current logo.</p>
            @error('logo') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Website</label>
            <input type="url" name="website" value="{{ old('website', $brand->website) }}" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('website') border-[var(--color-red)] @enderror" placeholder="https://example.com">
            @error('website') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Description</label>
            <textarea name="description" id="description-editor" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)]" rows="3">{{ old('description', $brand->description) }}</textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Sort Order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $brand->sort_order) }}" min="0" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('sort_order') border-[var(--color-red)] @enderror">
                @error('sort_order') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 cursor-pointer pb-2.5">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $brand->is_active) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    <span class="text-sm text-[var(--color-ink)]">Active</span>
                </label>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Meta Title</label>
            <input type="text" name="meta_title" value="{{ old('meta_title', $brand->meta_title) }}" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('meta_title') border-[var(--color-red)] @enderror">
            @error('meta_title') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Meta Description</label>
            <textarea name="meta_description" rows="2" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('meta_description') border-[var(--color-red)] @enderror">{{ old('meta_description', $brand->meta_description) }}</textarea>
            @error('meta_description') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end gap-2 pt-4">
            <a href="{{ route('admin.brands.index') }}" class="px-4 py-2 border hairline rounded text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-paper)] transition">Cancel</a>
            <button type="submit" class="bg-[var(--color-red)] text-white px-4 py-2 rounded font-medium hover:bg-[var(--color-red-dark)] transition">Update Brand</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
    window.ckeditorSync(CKEDITOR.replace("description-editor"));
</script>
@endpush
