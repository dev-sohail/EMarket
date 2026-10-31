@extends('layouts.store')
@section('title', $post->meta_title ?: $post->title . ' — ' . setting('store_name', config('app.name')))
@section('meta_description', $post->meta_description ?: $post->excerpt ?: '')

@php
    $rawContent = $post->content ?: '';
    // Sanitised on render as well as on submit: rows written before the
    // SafeHtml rule existed still have to be neutralised. The regex block-list
    // this replaced required a quoted attribute value, so
    // `<img src=x onerror=alert(1)>` passed through it untouched.
    $cleanedContent = \App\Rules\SafeHtml::sanitize($rawContent, \App\Rules\SafeHtml::PROFILE_CONTENT);
    $words = str_word_count(strip_tags($cleanedContent));
    $minutes = max(1, (int) ceil($words / 200));
    $shareUrl = urlencode(request()->fullUrl());
    $shareTitle = urlencode($post->title);
    $previousPost = \App\Models\BlogPost::visibleOnSite()
        ->where('published_at', '<', $post->published_at)
        ->latest('published_at')
        ->first();
    $nextPost = \App\Models\BlogPost::visibleOnSite()
        ->where('published_at', '>', $post->published_at)
        ->oldest('published_at')
        ->first();
@endphp

@push('styles')
<style>
    .blog-content { font-size: 17px; line-height: 1.85; color: #363636; }
    .blog-content > * + * { margin-top: 1.35rem; }
    .blog-content p { margin-top: 1.4rem; }
    .blog-content h1, .blog-content h2, .blog-content h3, .blog-content h4 {
        font-family: var(--font-display); color: var(--color-ink); font-weight: 700; line-height: 1.25;
    }
    .blog-content h2 { font-size: 30px; margin-top: 3rem; }
    .blog-content h3 { font-size: 23px; margin-top: 2.5rem; line-height: 1.3; }
    .blog-content h4 { font-size: 19px; margin-top: 2rem; }
    .blog-content strong { color: var(--color-ink); font-weight: 700; }
    .blog-content a { color: var(--color-red); text-decoration: underline; text-underline-offset: 3px; }
    .blog-content ul, .blog-content ol { padding-left: 1.5rem; }
    .blog-content li { margin-top: .65rem; padding-left: .25rem; color: #363636; }
    .blog-content blockquote {
        margin: 2.5rem 0; padding: 1.25rem 1.5rem;
        border-left: 3px solid var(--color-red);
        background: var(--color-paper-dim); color: #444;
        font-family: var(--font-display); font-size: 21px; line-height: 1.55; font-style: italic;
        border-radius: 0 0.375rem 0.375rem 0;
    }
    .blog-content img { width: 100%; border-radius: 12px; margin: 2.5rem 0; border: 1px solid var(--color-line); }
    .blog-content figure { margin: 2.5rem 0; }
    .blog-content figcaption { font-size: 14px; color: var(--color-steel); text-align: center; margin-top: 0.5rem; }
    .blog-content pre, .blog-content code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .blog-content code { background: var(--color-paper-dim); padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.95em; color: var(--color-ink); }
    .blog-content pre { background: var(--color-ink); color: #faf9f6; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; }
    .blog-content pre code { background: transparent; color: inherit; padding: 0; }
    .blog-content table { width: 100%; border-collapse: collapse; }
    .blog-content th, .blog-content td { border: 1px solid var(--color-line); padding: 0.5rem 0.75rem; }
    .blog-content th { background: var(--color-paper-dim); text-align: left; }
    .blog-content hr { border: none; border-top: 1px solid var(--color-line); margin: 2rem 0; }
    @media (max-width: 640px) {
        .blog-content { font-size: 16px; line-height: 1.75; }
        .blog-content h2 { font-size: 26px; }
        .blog-content h3 { font-size: 21px; }
        .blog-content blockquote { font-size: 18px; padding: 1rem 1.1rem; }
    }

    /* Reading progress */
    #reading-progress {
        position: fixed; top: 0; left: 0; height: 3px; width: 0%;
        background: var(--color-red); z-index: 9999;
        transition: width .08s linear;
    }

    /* Hero image */
    .hero-image { transition: transform .6s cubic-bezier(.2,.7,.2,1); }
    .hero-image-wrap:hover .hero-image { transform: scale(1.015); }

    /* Share buttons */
    .share-btn { transition: transform .2s ease, background-color .2s ease, color .2s ease, border-color .2s ease; }
    .share-btn:hover { transform: translateY(-2px); }

    /* Author card */
    .author-card { background: linear-gradient(135deg, #fafaf9, #f5f5f3); }

    /* Related cards */
    .related-card { display: block; transition: transform .25s ease, box-shadow .25s ease; }
    .related-card:hover { transform: translateY(-4px); box-shadow: 0 18px 40px rgba(0,0,0,.08); }
    .related-card img { transition: transform .5s ease; }
    .related-card:hover img { transform: scale(1.04); }

    /* Desktop share rail */
    .share-rail { position: absolute; left: -76px; top: 0; }
    @media (max-width: 1280px) { .share-rail { display: none; } }
</style>
@endpush

@section('content')
<div id="reading-progress"></div>

<main>
<article>

    {{-- ============================================
         ARTICLE HEADER
    ============================================= --}}
    <header class="bg-[var(--color-paper-dim)] border-b hairline">
        <div class="max-w-4xl mx-auto px-5 sm:px-6 pt-10 sm:pt-14 pb-16">

            {{-- Breadcrumb --}}
            <nav class="flex items-center justify-center gap-2 text-xs text-[var(--color-steel)] mb-8" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-[var(--color-ink)] transition">Home</a>
                <i class="fa-solid fa-chevron-right text-[8px] opacity-50"></i>
                <a href="{{ route('blog.index') }}" class="hover:text-[var(--color-ink)] transition">Blog</a>
                <i class="fa-solid fa-chevron-right text-[8px] opacity-50"></i>
                <span class="text-[var(--color-ink)]">Article</span>
            </nav>

            {{-- Eyebrow --}}
            <div class="text-center">
                <span class="eyebrow">Article</span>
            </div>

            {{-- Title --}}
            <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold text-[var(--color-ink)] leading-[1.08] tracking-tight text-center max-w-4xl mx-auto mt-5">
                {{ $post->title }}
            </h1>

            {{-- Excerpt --}}
            @if($post->excerpt)
                <p class="text-base sm:text-lg text-[var(--color-steel)] leading-relaxed text-center max-w-2xl mx-auto mt-6">
                    {{ $post->excerpt }}
                </p>
            @endif

            {{-- Meta --}}
            <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-3 mt-9 text-sm text-[var(--color-steel)]">
                @if($post->author)
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-full bg-[var(--color-ink)] text-white flex items-center justify-center text-xs font-bold">
                            {{ strtoupper(substr($post->author->name, 0, 1)) }}
                        </div>
                        <span class="font-semibold text-[var(--color-ink)]">{{ $post->author->name }}</span>
                    </div>
                    <span class="hidden sm:block">·</span>
                @endif
                <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('F j, Y') }}</time>
                <span>·</span>
                <span>{{ $post->readingTime() }} min read</span>
                <span>·</span>
                <span>{{ $post->views }} views</span>
            </div>
        </div>
    </header>

    {{-- ============================================
         HERO IMAGE
    ============================================= --}}
    @if($post->featured_image)
        <div class="max-w-5xl mx-auto px-4 sm:px-6 -mt-7 sm:-mt-10">
            <div class="hero-image-wrap relative overflow-hidden rounded-lg border hairline bg-[var(--color-paper)]">
                <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}"
                     class="hero-image w-full aspect-[16/8] object-cover">
            </div>
            @if($post->excerpt)
                <p class="text-xs text-[var(--color-steel)] text-center mt-3">{{ $post->excerpt }}</p>
            @endif
        </div>
    @endif

    {{-- ============================================
         ARTICLE BODY
    ============================================= --}}
    <div class="max-w-6xl mx-auto px-5 sm:px-6">
        <div class="relative max-w-3xl mx-auto pt-12 sm:pt-16 pb-16">

            {{-- Desktop share rail --}}
            <aside class="share-rail">
                <div class="sticky top-28">
                    <p class="text-[10px] uppercase tracking-[.15em] font-bold text-[var(--color-steel)] mb-3 text-center">Share</p>
                    <div class="flex flex-col gap-2">
                        <button type="button" data-action="shareWhatsApp" class="share-btn w-10 h-10 rounded-full border hairline flex items-center justify-center text-[var(--color-steel)] hover:bg-[#25D366] hover:text-white hover:border-[#25D366]" title="Share on WhatsApp">
                            <i class="fa-brands fa-whatsapp"></i>
                        </button>
                        <button type="button" data-action="shareFacebook" class="share-btn w-10 h-10 rounded-full border hairline flex items-center justify-center text-[var(--color-steel)] hover:bg-[#1877F2] hover:text-white hover:border-[#1877F2]" title="Share on Facebook">
                            <i class="fa-brands fa-facebook-f"></i>
                        </button>
                        <button type="button" data-action="shareTwitter" class="share-btn w-10 h-10 rounded-full border hairline flex items-center justify-center text-[var(--color-steel)] hover:bg-black hover:text-white hover:border-black" title="Share on X">
                            <i class="fa-brands fa-x-twitter"></i>
                        </button>
                        <button type="button" data-action="copyLink" class="share-btn w-10 h-10 rounded-full border hairline flex items-center justify-center text-[var(--color-steel)] hover:bg-[var(--color-red)] hover:text-white hover:border-[var(--color-red)]" title="Copy link">
                            <i class="fa-solid fa-link"></i>
                        </button>
                    </div>
                </div>
            </aside>

            {{-- Content --}}
            <div id="article-content" class="blog-content">
                {!! $cleanedContent !!}
            </div>

            {{-- Mobile share --}}
            <div class="lg:hidden mt-12 pt-7 border-t hairline">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-xs uppercase tracking-wider font-semibold text-[var(--color-steel)]">Share this article</span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-action="shareWhatsApp" class="share-btn w-9 h-9 rounded-full border hairline flex items-center justify-center hover:bg-[#25D366] hover:text-white hover:border-[#25D366]">
                            <i class="fa-brands fa-whatsapp"></i>
                        </button>
                        <button type="button" data-action="shareFacebook" class="share-btn w-9 h-9 rounded-full border hairline flex items-center justify-center hover:bg-[#1877F2] hover:text-white hover:border-[#1877F2]">
                            <i class="fa-brands fa-facebook-f"></i>
                        </button>
                        <button type="button" data-action="shareTwitter" class="share-btn w-9 h-9 rounded-full border hairline flex items-center justify-center hover:bg-black hover:text-white hover:border-black">
                            <i class="fa-brands fa-x-twitter"></i>
                        </button>
                        <button type="button" data-action="copyLink" class="share-btn w-9 h-9 rounded-full border hairline flex items-center justify-center hover:bg-[var(--color-red)] hover:text-white hover:border-[var(--color-red)]">
                            <i class="fa-solid fa-link"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Author card --}}
            @if($post->author)
                <div class="author-card border hairline rounded-lg p-5 sm:p-6 mt-14">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-full bg-[var(--color-ink)] text-white flex items-center justify-center font-bold">
                            {{ strtoupper(substr($post->author->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs uppercase tracking-wider text-[var(--color-steel)] font-semibold">Written by</p>
                            <h3 class="font-semibold text-[var(--color-ink)] mt-1">{{ $post->author->name }}</h3>
                            <p class="text-sm text-[var(--color-steel)] leading-relaxed mt-1.5">
                                Sharing practical insights, ideas, and useful knowledge through the blog.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Prev / Next --}}
            @if($previousPost || $nextPost)
                <div class="grid grid-cols-1 sm:grid-cols-2 border-t border-b hairline mt-14">
                    @if($previousPost)
                        <a href="{{ route('blog.show', $previousPost->slug) }}" class="group py-6 sm:pr-7 sm:border-r hairline">
                            <span class="text-xs text-[var(--color-steel)]"><i class="fa-solid fa-arrow-left mr-2"></i>Previous article</span>
                            <span class="block font-display font-semibold text-lg mt-2 group-hover:text-[var(--color-red)] transition">{{ $previousPost->title }}</span>
                        </a>
                    @else
                        <span class="hidden sm:block"></span>
                    @endif
                    @if($nextPost)
                        <a href="{{ route('blog.show', $nextPost->slug) }}" class="group py-6 sm:pl-7 text-left sm:text-right">
                            <span class="text-xs text-[var(--color-steel)]">Next article<i class="fa-solid fa-arrow-right ml-2"></i></span>
                            <span class="block font-display font-semibold text-lg mt-2 group-hover:text-[var(--color-red)] transition">{{ $nextPost->title }}</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- ============================================
         RELATED ARTICLES
    ============================================= --}}
    @if($related->count())
        <section class="bg-[var(--color-paper-dim)] border-t hairline py-16 sm:py-20">
            <div class="max-w-6xl mx-auto px-5 sm:px-6">
                <div class="flex items-end justify-between gap-5 mb-8">
                    <div>
                        <span class="eyebrow">Continue reading</span>
                        <h2 class="font-display text-3xl sm:text-4xl font-bold mt-2">Related articles</h2>
                    </div>
                    <a href="{{ route('blog.index') }}" class="hidden sm:inline-flex text-sm font-semibold text-[var(--color-red)] hover:text-[var(--color-red-dark)]">
                        View all <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($related as $item)
                        @php $rw = str_word_count(strip_tags($item->content)); @endphp
                        <a href="{{ route('blog.show', $item->slug) }}" class="related-card group block">
                            <div class="bg-[var(--color-paper)] border hairline rounded-lg overflow-hidden hover:border-[var(--color-ink)] transition flex flex-col">
                                <div class="aspect-[16/9] overflow-hidden bg-[var(--color-paper-dim)]">
                                    @if($item->featured_image)
                                        <img src="{{ asset('storage/' . $item->featured_image) }}" alt="{{ $item->title }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-[var(--color-steel)]"><i class="fa-regular fa-image text-3xl"></i></div>
                                    @endif
                                </div>
                                <div class="p-5">
                                    <span class="eyebrow">Article</span>
                                    <h3 class="font-display text-xl font-bold mt-2 group-hover:text-[var(--color-red)] transition line-clamp-2">{{ $item->title }}</h3>
                                    @if($item->excerpt)
                                        <p class="text-sm text-[var(--color-steel)] mt-2 leading-relaxed line-clamp-2">{{ $item->excerpt }}</p>
                                    @endif
                                    <div class="text-xs text-[var(--color-steel)] mt-4">
                                        {{ $item->readingTime() }} min read · {{ $item->published_at?->format('M j, Y') }} · {{ $item->views }} views
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============================================
         BACK TO BLOG
    ============================================= --}}
    <div class="max-w-6xl mx-auto px-5 sm:px-6">
        <div class="py-8 flex justify-center">
            <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-red)] hover:text-[var(--color-red-dark)]">
                <i class="fa-solid fa-arrow-left text-xs"></i> Back to all articles
            </a>
        </div>
    </div>

</article>
</main>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
    /* Reading progress */
    window.addEventListener('scroll', function () {
        const article = document.querySelector('#article-content');
        const progress = document.querySelector('#reading-progress');
        if (!article || !progress) return;
        const articleTop = article.getBoundingClientRect().top + window.scrollY;
        const articleHeight = article.offsetHeight;
        const scrollPosition = window.scrollY - articleTop;
        const percentage = (scrollPosition / (articleHeight - window.innerHeight)) * 100;
        progress.style.width = Math.min(100, Math.max(0, percentage)) + '%';
    });

    function getArticleUrl() { return window.location.href; }

    function shareWhatsApp() {
        const url = encodeURIComponent(getArticleUrl());
        const title = encodeURIComponent(document.title);
        window.open('https://wa.me/?text=' + title + '%20' + url, '_blank', 'noopener,noreferrer');
    }
    function shareFacebook() {
        const url = encodeURIComponent(getArticleUrl());
        window.open('https://www.facebook.com/sharer/sharer.php?u=' + url, '_blank', 'noopener,noreferrer');
    }
    function shareTwitter() {
        const url = encodeURIComponent(getArticleUrl());
        const title = encodeURIComponent(document.title);
        window.open('https://twitter.com/intent/tweet?url=' + url + '&text=' + title, '_blank', 'noopener,noreferrer');
    }

    async function copyLink() {
        const url = getArticleUrl();
        try {
            await navigator.clipboard.writeText(url);
            showToast('Article link copied');
        } catch (e) {
            showToast('Unable to copy link', 'error');
        }
    }
</script>
@endpush
