@extends('layouts.admin')
@section('title', 'Edit Blog Post')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Edit Blog Post</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Update your blog post.</p>
    </div>
    <a href="{{ route('admin.blogs.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]"><i class="fa-solid fa-arrow-left mr-1"></i> Back</a>
</div>
@endsection
@section('content')
<form method="POST" action="{{ route('admin.blogs.update', $blogPost) }}" enctype="multipart/form-data">
@csrf @method('PUT')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Title *</label>
                <input type="text" name="title" value="{{ old('title', $blogPost->title) }}" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('title') border-[var(--color-red)] @enderror">
                @error('title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $blogPost->slug) }}" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('slug') border-[var(--color-red)] @enderror">
                @error('slug') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Content *</label>
                <textarea name="content" id="content-editor" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('content') border-[var(--color-red)] @enderror" rows="12">{{ old('content', $blogPost->content) }}</textarea>
                @error('content') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Excerpt</label>
                <textarea name="excerpt" rows="3" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('excerpt') border-[var(--color-red)] @enderror">{{ old('excerpt', $blogPost->excerpt) }}</textarea>
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
                    <option value="draft" {{ old('status', $blogPost->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="published" {{ old('status', $blogPost->status) === 'published' ? 'selected' : '' }}>Published</option>
                </select>
                @error('status') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Publish Date</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at', $blogPost->published_at?->format('Y-m-d\TH:i')) }}" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('published_at') border-[var(--color-red)] @enderror">
                @error('published_at') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-3 cursor-pointer pt-2 border-t hairline">
                <input type="hidden" name="show_on_site" value="0">
                <input type="checkbox" name="show_on_site" value="1" {{ old('show_on_site', $blogPost->show_on_site) ? 'checked' : '' }} class="w-5 h-5 rounded border-gray-300 text-[var(--color-red)] focus:ring-[var(--color-red)]">
                <span>
                    <span class="block text-sm font-medium text-[var(--color-ink)]">Show on storefront</span>
                    <span class="block text-xs text-[var(--color-steel)]">Post must also be Published and the Blogs feature enabled in Settings.</span>
                </span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer pt-2 border-t hairline">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $blogPost->is_featured) ? 'checked' : '' }} class="w-5 h-5 rounded border-gray-300 text-[var(--color-red)] focus:ring-[var(--color-red)]">
                <span>
                    <span class="block text-sm font-medium text-[var(--color-ink)]">Featured post</span>
                    <span class="block text-xs text-[var(--color-steel)]">Featured posts appear at the top of the blog listing.</span>
                </span>
            </label>
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-4">
            <h2 class="font-semibold text-[var(--color-ink)]">Featured Image</h2>
            @if($blogPost->featured_image)
                <img src="{{ asset('storage/' . $blogPost->featured_image) }}" class="w-full h-32 object-cover rounded-lg border hairline mb-3">
            @endif
            <input type="file" name="featured_image" accept="image/*" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('featured_image') border-[var(--color-red)] @enderror">
            @error('featured_image') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
        </div>
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-4">
            <h2 class="font-semibold text-[var(--color-ink)]">SEO</h2>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Meta Title</label>
                <input type="text" name="meta_title" value="{{ old('meta_title', $blogPost->meta_title) }}" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('meta_title') border-[var(--color-red)] @enderror">
                @error('meta_title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Meta Description</label>
                <textarea name="meta_description" rows="3" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('meta_description') border-[var(--color-red)] @enderror">{{ old('meta_description', $blogPost->meta_description) }}</textarea>
                @error('meta_description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.blogs.index') }}" class="px-6 py-2.5 rounded-lg text-sm font-medium border hairline hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
            <button type="submit" class="bg-[var(--color-red)] text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                <i class="fa-solid fa-save mr-1"></i> Update Post
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
