@extends('layouts.admin')
@section('title', $blogPost->title ?? 'Blog Post')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">{{ $blogPost->title }}</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Blog post details.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.blogs.edit', $blogPost) }}" class="bg-[var(--color-red)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition">Edit</a>
        <a href="{{ route('admin.blogs.index') }}" class="text-sm text-[var(--color-steel)] hover:text-[var(--color-ink)]"><i class="fa-solid fa-arrow-left mr-1"></i> Back</a>
    </div>
</div>
@endsection
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            @if($blogPost->featured_image)
                <img src="{{ asset('storage/' . $blogPost->featured_image) }}" class="w-full h-64 object-cover rounded-lg mb-4">
            @endif
            <div class="text-sm text-[var(--color-ink-soft)] whitespace-pre-wrap">{{ $blogPost->content }}</div>
        </div>
    </div>
    <div class="space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-3">
            <h2 class="font-semibold text-[var(--color-ink)]">Details</h2>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between">
                    <dt class="text-[var(--color-steel)]">Status</dt>
                    <dd><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $blogPost->status === 'published' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">{{ ucfirst($blogPost->status) }}</span></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-[var(--color-steel)]">On Site</dt>
                    <dd><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $blogPost->show_on_site ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">{{ $blogPost->show_on_site ? 'Yes' : 'No' }}</span></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-[var(--color-steel)]">Featured</dt>
                    <dd><span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $blogPost->is_featured ? 'bg-[var(--color-red)]/10 text-[var(--color-red)]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">{{ $blogPost->is_featured ? 'Yes' : 'No' }}</span></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-[var(--color-steel)]">Author</dt>
                    <dd class="text-[var(--color-ink)]">{{ $blogPost->author?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-[var(--color-steel)]">Published</dt>
                    <dd class="text-[var(--color-ink)]">{{ $blogPost->published_at?->format('M d, Y H:i') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-[var(--color-steel)]">Created</dt>
                    <dd class="text-[var(--color-ink)]">{{ $blogPost->created_at?->format('M d, Y') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-[var(--color-steel)]">Slug</dt>
                    <dd class="text-[var(--color-ink)] text-xs font-mono">{{ $blogPost->slug }}</dd>
                </div>
            </dl>
        </div>
        @if($blogPost->excerpt)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-2">Excerpt</h2>
                <p class="text-sm text-[var(--color-steel)]">{{ $blogPost->excerpt }}</p>
            </div>
        @endif
        @if($blogPost->meta_title || $blogPost->meta_description)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-3">
                <h2 class="font-semibold text-[var(--color-ink)]">SEO</h2>
                @if($blogPost->meta_title)
                    <div>
                        <p class="text-xs text-[var(--color-steel)]">Meta Title</p>
                        <p class="text-sm text-[var(--color-ink)]">{{ $blogPost->meta_title }}</p>
                    </div>
                @endif
                @if($blogPost->meta_description)
                    <div>
                        <p class="text-xs text-[var(--color-steel)]">Meta Description</p>
                        <p class="text-sm text-[var(--color-ink)]">{{ $blogPost->meta_description }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
