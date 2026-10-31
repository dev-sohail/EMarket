@extends('layouts.store')

@section('title', $page->title ?? 'Page')
@section('meta_description', $page->meta_description ?? '')

@section('content')
<article class="max-w-4xl mx-auto px-4 py-12">
    <div class="mb-10">
        <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
            <a href="{{ route('home') }}" class="hover:text-[var(--color-red)] transition">Home</a>
            <span>/</span>
            <span class="text-[var(--color-ink)]">{{ $page->title ?? 'Page' }}</span>
        </nav>
        <h1 class="font-display text-4xl sm:text-5xl font-bold text-[var(--color-ink)] leading-tight">{{ $page->title ?? '' }}</h1>
    </div>

    <div class="bg-white rounded-lg border hairline p-6 sm:p-10">
        <div class="page-content space-y-6 text-[var(--color-steel)] leading-relaxed">
            {{-- Sanitised on render as well as on submit: rows written before
                 the SafeHtml rule existed still have to be neutralised. --}}
            {!! \App\Rules\SafeHtml::sanitize($page->content ?? '', \App\Rules\SafeHtml::PROFILE_CONTENT) !!}
        </div>
    </div>
</article>

@push('styles')
<style>
.page-content h1 { font-size: 1.875rem; font-weight: 700; color: var(--color-ink); margin-top: 2rem; margin-bottom: 1rem; font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
.page-content h2 { font-size: 1.5rem; font-weight: 600; color: var(--color-ink); margin-top: 2rem; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--color-line); font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
.page-content h3 { font-size: 1.25rem; font-weight: 600; color: var(--color-ink); margin-top: 1.5rem; margin-bottom: 0.5rem; font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
.page-content h4 { font-size: 1.125rem; font-weight: 600; color: var(--color-ink); margin-top: 1.25rem; margin-bottom: 0.5rem; }
.page-content p { margin-bottom: 1rem; line-height: 1.75; }
.page-content a { color: var(--color-red); text-decoration: none; }
.page-content a:hover { text-decoration: underline; }
.page-content strong { color: var(--color-ink); font-weight: 600; }
.page-content ul, .page-content ol { margin-bottom: 1rem; padding-left: 1.5rem; }
.page-content ul { list-style-type: disc; }
.page-content ol { list-style-type: decimal; }
.page-content li { margin-bottom: 0.5rem; padding-left: 0.25rem; }
.page-content li::marker { color: var(--color-red); }
.page-content blockquote { border-left: 4px solid var(--color-red); padding-left: 1rem; margin: 1.5rem 0; font-style: italic; color: var(--color-steel); }
.page-content pre { background: var(--color-ink); color: white; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin: 1rem 0; }
.page-content code { background: var(--color-paper-dim); color: var(--color-red); padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
.page-content table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; border: 1px solid var(--color-line); border-radius: 0.5rem; overflow: hidden; }
.page-content th { background: var(--color-paper-dim); text-align: left; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 600; color: var(--color-ink); }
.page-content td { padding: 0.75rem 1rem; font-size: 0.875rem; color: var(--color-steel); border-top: 1px solid var(--color-line); }
.page-content hr { border: none; border-top: 1px solid var(--color-line); margin: 2rem 0; }
.page-content img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 1rem 0; }
</style>
@endpush
@endsection
