@extends('layouts.store')

@section('title', 'FAQ')

@section('meta_description', 'Frequently asked questions about our store.')

@section('content')
<article class="max-w-4xl mx-auto px-4 py-12">
    <div class="mb-10">
        <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
            <a href="{{ route('home') }}" class="hover:text-[var(--color-red)] transition">Home</a>
            <span>/</span>
            <span class="text-[var(--color-ink)]">FAQ</span>
        </nav>
        <h1 class="font-display text-4xl sm:text-5xl font-bold text-[var(--color-ink)] leading-tight">Frequently Asked Questions</h1>
    </div>

    <div class="space-y-4">
        @forelse($faqs as $faq)
            <details class="bg-white rounded-lg border hairline group">
                <summary class="flex items-center justify-between px-6 py-4 cursor-pointer list-none">
                    <span class="font-medium text-[var(--color-ink)]">{{ $faq->question }}</span>
                    <svg class="w-5 h-5 text-[var(--color-steel)] transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-6 pb-4 text-[var(--color-steel)]">
                    {!! nl2br(e($faq->answer)) !!}
                </div>
            </details>
        @empty
            <p class="text-[var(--color-steel)]">No FAQs available at the moment.</p>
        @endforelse
    </div>
</article>
@endsection
