@extends('layouts.admin')
@section('title', 'Blog Posts')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Blog Posts</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Manage your blog content.</p>
    </div>
    <a href="{{ route('admin.blogs.create') }}" class="bg-[var(--color-red)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
        <i class="fa-solid fa-plus mr-1"></i> New Post
    </a>
</div>
@endsection
@section('content')
@if(setting('blogs_enabled'))
    <div class="bg-[#1B7A43]/10 border border-[#1B7A43]/30 text-[#1B7A43] px-4 py-3 rounded-lg text-sm flex items-center gap-2 mb-4">
        <i class="fa-solid fa-circle-info"></i>
        Blog section is visible on the storefront. Manage this in
        <a href="{{ route('admin.settings.index') }}" class="underline">Settings</a>.
    </div>
@else
    <div class="bg-[var(--color-paper-dim)] border border-[var(--color-line)] text-[var(--color-ink-soft)] px-4 py-3 rounded-lg text-sm flex items-center gap-2 mb-4">
        <i class="fa-solid fa-circle-info"></i>
        Blog section is currently <strong class="mx-1">hidden</strong> site-wide. Enable it in
        <a href="{{ route('admin.settings.index') }}" class="underline text-[var(--color-red)]">Settings</a>.
    </div>
@endif
<div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
    <div class="p-4 border-b hairline flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('admin.blogs.index') }}" class="flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search posts..." class="w-full border hairline bg-[var(--color-paper)] px-4 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]">
        </form>
        <div class="flex gap-2">
            <a href="{{ route('admin.blogs.index') }}" class="px-3 py-2 rounded text-sm {{ !request('status') ? 'bg-[var(--color-red)] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] hover:bg-[var(--color-line)]' }}">All</a>
            <a href="{{ route('admin.blogs.index', ['status' => 'published']) }}" class="px-3 py-2 rounded text-sm {{ request('status') === 'published' ? 'bg-[#1B7A43] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] hover:bg-[var(--color-line)]' }}">Published</a>
            <a href="{{ route('admin.blogs.index', ['status' => 'draft']) }}" class="px-3 py-2 rounded text-sm {{ request('status') === 'draft' ? 'bg-[var(--color-paper-dim)] text-white' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] hover:bg-[var(--color-line)]' }}">Draft</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[var(--color-paper-dim)]">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Title</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Author</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">On Site</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Featured</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Date</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Views</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Read</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-[var(--color-steel)] uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-line)]">
                @forelse($posts as $post)
                    <tr class="hover:bg-[var(--color-paper-dim)]">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.blogs.show', $post) }}" class="font-medium text-[var(--color-ink)] hover:text-[var(--color-red)] text-sm">{{ Str::limit($post->title, 50) }}</a>
                            <p class="text-xs text-[var(--color-steel)] mt-0.5">{{ Str::limit($post->excerpt, 60) }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $post->author?->name ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $post->status === 'published' ? 'bg-[#1B7A43]/10 text-[#1B7A43]' : 'bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)]' }}">{{ ucfirst($post->status) }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.blogs.toggle-show-on-site', $post) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-2 text-xs">
                                    <span class="relative inline-block w-9 h-5 rounded-full transition {{ $post->show_on_site ? 'bg-[#1B7A43]' : 'bg-[var(--color-line)]' }}">
                                        <span class="absolute top-0.5 {{ $post->show_on_site ? 'right-0.5' : 'left-0.5' }} w-4 h-4 bg-white rounded-full transition-all"></span>
                                    </span>
                                    <span class="{{ $post->show_on_site ? 'text-[#1B7A43]' : 'text-[var(--color-steel)]' }}">{{ $post->show_on_site ? 'On' : 'Off' }}</span>
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('admin.blogs.toggle-featured', $post) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-2 text-xs">
                                    <span class="relative inline-block w-9 h-5 rounded-full transition {{ $post->is_featured ? 'bg-[var(--color-red)]' : 'bg-[var(--color-line)]' }}">
                                        <span class="absolute top-0.5 {{ $post->is_featured ? 'right-0.5' : 'left-0.5' }} w-4 h-4 bg-white rounded-full transition-all"></span>
                                    </span>
                                    <span class="{{ $post->is_featured ? 'text-[var(--color-red)]' : 'text-[var(--color-steel)]' }}">{{ $post->is_featured ? 'Yes' : 'No' }}</span>
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $post->created_at?->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-ink)]">{{ $post->views }}</td>
                        <td class="px-6 py-4 text-sm text-[var(--color-steel)]">{{ $post->readingTime() }} min</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.blogs.show', $post) }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">View</a>
                                <a href="{{ route('admin.blogs.edit', $post) }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.blogs.destroy', $post) }}" data-confirm="Delete this post?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] text-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-8 text-center text-[var(--color-steel)]">No blog posts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($posts->hasPages())
        <div class="p-4 border-t hairline">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
