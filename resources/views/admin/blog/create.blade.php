@extends('layouts.admin')
@section('title', 'New Blog Post')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">New Blog Post</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Create a new blog post.</p>
    </div>
    <a href="{{ route('admin.blogs.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]"><i class="fa-solid fa-arrow-left mr-1"></i> Back</a>
</div>
@endsection
@section('content')
<form method="POST" action="{{ route('admin.blogs.store') }}" enctype="multipart/form-data">
@csrf
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Title *</label>
                <input type="text" name="title" value="{{ old('title') }}" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('title') border-[var(--color-red)] @enderror">
                @error('title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug') }}" placeholder="Auto-generated from title" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('slug') border-[var(--color-red)] @enderror">
                @error('slug') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Content *</label>
                <textarea name="content" id="content-editor" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('content') border-[var(--color-red)] @enderror" rows="12">{{ old('content') }}</textarea>
                @error('content') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Excerpt</label>
                <textarea name="excerpt" rows="3" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('excerpt') border-[var(--color-red)] @enderror">{{ old('excerpt') }}</textarea>
                @error('excerpt') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
    <div class="space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-4">
            <h2 class="font-semibold text-[var(--color-ink)]">Publish</h2>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Status *</label>
                <select name="status" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('status') border-[var(--color-red)] @enderror">
                    <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>Published</option>
                </select>
                @error('status') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Publish Date</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at') }}" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('published_at') border-[var(--color-red)] @enderror">
                @error('published_at') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-3 cursor-pointer pt-2 border-t hairline">
                <input type="hidden" name="show_on_site" value="0">
                <input type="checkbox" name="show_on_site" value="1" {{ old('show_on_site') ? 'checked' : '' }} class="w-5 h-5 rounded border-gray-300 text-[var(--color-red)] focus:ring-[var(--color-red)]">
                <span>
                    <span class="block text-sm font-medium text-[var(--color-ink)]">Show on storefront</span>
                    <span class="block text-xs text-[var(--color-steel)]">Post must also be Published and the Blogs feature enabled in Settings.</span>
                </span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer pt-2 border-t hairline">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }} class="w-5 h-5 rounded border-gray-300 text-[var(--color-red)] focus:ring-[var(--color-red)]">
                <span>
                    <span class="block text-sm font-medium text-[var(--color-ink)]">Featured post</span>
                    <span class="block text-xs text-[var(--color-steel)]">Featured posts appear at the top of the blog listing.</span>
                </span>
            </label>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-4">
            <h2 class="font-semibold text-[var(--color-ink)]">Featured Image</h2>
            <input type="file" name="featured_image" accept="image/*" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('featured_image') border-[var(--color-red)] @enderror">
            @error('featured_image') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-4">
            <h2 class="font-semibold text-[var(--color-ink)]">SEO</h2>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title') }}" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('meta_title') border-[var(--color-red)] @enderror">
                @error('meta_title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Meta Description</label>
                <textarea name="meta_description" rows="3" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('meta_description') border-[var(--color-red)] @enderror">{{ old('meta_description') }}</textarea>
                @error('meta_description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-[var(--color-red)] text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                <i class="fa-solid fa-save mr-1"></i> Create Post
            </button>
        </div>
    </div>
</div>
</form>
@push('scripts')
<script src="{{ asset('vendor/ckeditor/ckeditor.js') }}"></script>
<script nonce="{{ $cspNonce }}">
    window.ckeditorSync(CKEDITOR.replace("content-editor"));
</script>
@endpush
@endsection
