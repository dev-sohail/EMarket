@extends('layouts.store')
@section('title', 'Blog — ' . setting('store_name', config('app.name')))
@section('meta_description', setting('store_description', 'Read our latest news, tips, and stories.'))

@section('hero')
<section class="bg-[var(--color-paper-dim)] border-b hairline">
    <div class="max-w-6xl mx-auto px-5 sm:px-6 py-14 sm:py-20">
        <div class="flex items-end justify-between gap-5 mb-10">
            <div>
                <span class="eyebrow">Journal</span>
                <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold text-[var(--color-ink)] mt-3 tracking-tight leading-[1.08]">
                    Stories &amp; Insights
                </h1>
                <p class="text-base sm:text-lg text-[var(--color-steel)] mt-4 max-w-xl leading-relaxed">
                    News, product deep-dives, and how-tos from our team.
                </p>
            </div>
            <form method="GET" action="{{ route('blog.index') }}" class="hidden md:flex items-center gap-2 flex-shrink-0">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--color-steel)]"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search articles..."
                           class="border hairline rounded-lg pl-11 pr-4 py-2.5 text-sm bg-[var(--color-paper)] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent w-64">
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
            </form>
        </div>

        {{-- Mobile search --}}
        <form method="GET" action="{{ route('blog.index') }}" class="md:hidden flex items-center gap-2">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--color-steel)]"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search articles..."
                       class="w-full border hairline rounded-lg pl-11 pr-4 py-2.5 text-sm bg-[var(--color-paper)] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        @if(request('search'))
            <p class="text-sm text-[var(--color-steel)] mt-6">
                Results for <strong class="text-[var(--color-ink)]">"{{ request('search') }}"</strong>
                — {{ $posts->total() }} {{ Str::plural('article', $posts->total()) }}
                · <a href="{{ route('blog.index') }}" class="text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">Clear search</a>
            </p>
        @endif
    </div>
</section>
@endsection

@section('content')
<div class="max-w-6xl mx-auto px-5 sm:px-6 py-12 sm:py-16">

    @if($posts->count())
        {{-- Featured --}}
        @if($featured && !request('search'))
            @php $fw = str_word_count(strip_tags($featured->content)); @endphp
            <a href="{{ route('blog.show', $featured->slug) }}" class="related-card group block mb-12 sm:mb-16">
                <div class="bg-[var(--color-paper)] border hairline rounded-lg overflow-hidden hover:border-[var(--color-ink)] transition">
                    <div class="grid grid-cols-1 lg:grid-cols-5">
                        <div class="lg:col-span-3 bg-[var(--color-paper-dim)] overflow-hidden flex items-center justify-center min-h-[260px] lg:min-h-[420px]">
                            @if($featured->featured_image)
                                <img src="{{ asset('storage/' . $featured->featured_image) }}" alt="{{ $featured->title }}" class="w-full h-full object-cover hero-image">
                            @else
                                <i class="fa-regular fa-image text-5xl text-[var(--color-steel)]"></i>
                            @endif
                        </div>
                        <div class="lg:col-span-2 p-6 sm:p-10 flex flex-col justify-center">
                            <span class="eyebrow">Featured</span>
                            <h2 class="font-display text-2xl sm:text-3xl lg:text-4xl font-bold text-[var(--color-ink)] leading-tight mt-3 group-hover:text-[var(--color-red)] transition">
                                {{ $featured->title }}
                            </h2>
                            @if($featured->excerpt)
                                <p class="text-[var(--color-steel)] mt-4 leading-relaxed line-clamp-3">{{ $featured->excerpt }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-2 mt-6 text-xs text-[var(--color-steel)]">
                                @if($featured->author)
                                    <span class="flex items-center gap-2">
                                        <span class="w-7 h-7 rounded-full bg-[var(--color-ink)] text-white flex items-center justify-center text-[10px] font-bold">{{ strtoupper(substr($featured->author->name, 0, 1)) }}</span>
                                        {{ $featured->author->name }}
                                    </span>
                                    <span>·</span>
                                @endif
                                <span>{{ $featured->published_at?->format('F j, Y') }}</span>
                                <span>·</span>
                                <span>{{ $featured->readingTime() }} min read</span>
                                <span>·</span>
                                <span>{{ $featured->views }} views</span>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @endif

        {{-- Section heading --}}
        <div class="flex items-end justify-between gap-5 mb-8">
            <div>
                <span class="eyebrow">{{ request('search') ? 'Results' : 'Latest articles' }}</span>
                <h2 class="font-display text-3xl sm:text-4xl font-bold text-[var(--color-ink)] mt-2">{{ $posts->total() }} {{ Str::plural('article', $posts->total()) }}</h2>
            </div>
        </div>

        {{-- Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($posts as $post)
                @if($featured && !request('search') && $post->getKey() === $featured->getKey()) @continue @endif
            @php $w = str_word_count(strip_tags($post->content)); @endphp
                <a href="{{ route('blog.show', $post->slug) }}" class="related-card group block">
                    <div class="bg-[var(--color-paper)] border hairline rounded-lg overflow-hidden flex flex-col hover:border-[var(--color-ink)] transition">
                        <div class="aspect-[16/9] overflow-hidden bg-[var(--color-paper-dim)]">
                            @if($post->featured_image)
                                <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-[var(--color-steel)]"><i class="fa-regular fa-image text-3xl"></i></div>
                            @endif
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <span class="eyebrow">Article</span>
                            <h3 class="font-display text-xl font-bold mt-2 group-hover:text-[var(--color-red)] transition line-clamp-2 leading-snug">
                                {{ $post->title }}
                            </h3>
                            @if($post->excerpt)
                                <p class="text-sm text-[var(--color-steel)] mt-2 line-clamp-3 flex-1 leading-relaxed">{{ $post->excerpt }}</p>
                            @endif
                            <div class="text-xs text-[var(--color-steel)] mt-4">
                                {{ $post->readingTime() }} min read · {{ $post->published_at?->format('M j, Y') }} · {{ $post->views }} views
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-12">{{ $posts->withQueryString()->links() }}</div>
    @else
        <div class="bg-[var(--color-paper)] border hairline rounded-lg p-16 text-center">
            <i class="fa-regular fa-newspaper text-5xl text-[var(--color-steel)]"></i>
            <h3 class="font-display text-xl font-semibold text-[var(--color-ink)] mt-4">
                {{ request('search') ? 'No matching articles' : 'No articles yet' }}
            </h3>
            <p class="text-[var(--color-steel)] mt-2 max-w-md mx-auto">
                {{ request('search') ? 'Try a different keyword or clear the search to browse all articles.' : 'Check back soon — we are working on new content.' }}
            </p>
            @if(request('search'))
                <a href="{{ route('blog.index') }}" class="btn btn-primary mt-6">Browse all articles</a>
            @endif
        </div>
    @endif
</div>
@endsection

@push('styles')
<style>
    .hero-image { transition: transform .6s cubic-bezier(.2,.7,.2,1); }
    .related-card { display: block; transition: transform .25s ease, box-shadow .25s ease; }
    .related-card:hover { transform: translateY(-4px); box-shadow: 0 18px 40px rgba(0,0,0,.08); }
    .related-card img { transition: transform .5s ease; }
    .related-card:hover img { transform: scale(1.04); }
</style>
@endpush
