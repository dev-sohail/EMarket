@extends('layouts.store')

@section('title', 'My Reviews')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" class="hover:text-[var(--color-red)]">Dashboard</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">My Reviews</span>
    </nav>

    <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">My Reviews</h1>

    <div class="space-y-4">
        @forelse($reviews ?? [] as $review)
            <div class="bg-white rounded-lg border hairline p-6">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <div class="flex">
                                @for($i = 1; $i <= 5; $i++)
                                    @if($i <= $review->rating)
                                        <i class="fa-solid fa-star text-base text-[var(--color-ink)]"></i>
                                    @else
                                        <i class="fa-regular fa-star text-base text-[var(--color-line)]"></i>
                                    @endif
                                @endfor
                            </div>
                            @if($review->title)
                                <h3 class="font-medium text-[var(--color-ink)]">{{ $review->title }}</h3>
                            @endif
                        </div>
                        <p class="text-sm text-[var(--color-steel)]">For: <a href="{{ route('product.show', $review->product->slug ?? '#') }}" class="text-[var(--color-red)] hover:underline">{{ $review->product->name ?? 'Product' }}</a></p>
                    </div>
                    <span class="text-sm text-[var(--color-steel)]">{{ $review->created_at->diffForHumans() }}</span>
                </div>
                <p class="mt-3 text-[var(--color-steel)]">{{ $review->comment }}</p>
                @if($review->status === \App\Enums\ReviewStatus::Approved)
                    <span class="inline-flex items-center gap-1 mt-2 text-xs text-[#1B7A43]">
                        <i class="fa-solid fa-check text-xs"></i>
                        Approved
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 mt-2 text-xs text-[var(--color-red-dark)]">Pending approval</span>
                @endif
            </div>
        @empty
            <div class="text-center py-16 bg-white rounded-lg border hairline">
                <i class="fa-regular fa-star text-5xl text-[var(--color-line)]"></i>
                <h3 class="text-lg font-medium text-[var(--color-ink)] mb-2">No reviews yet</h3>
                <p class="text-[var(--color-steel)]">Review products you've purchased to help other customers.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
