@extends('layouts.store')

@section('title', 'Write a Review')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    <nav class="flex items-center gap-2 text-sm text-[var(--color-steel)] mb-6">
        <a href="{{ route('home') }}" class="hover:text-[var(--color-red)]">Home</a>
        <span>/</span>
        <a href="{{ route('customer.reviews') }}" class="hover:text-[var(--color-red)]">My Reviews</a>
        <span>/</span>
        <span class="text-[var(--color-ink)]">Write Review</span>
    </nav>

    <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Write a Review</h1>

    @if(isset($product))
        <div class="bg-white rounded-lg border hairline p-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-[var(--color-paper-dim)] rounded-lg flex-shrink-0 flex items-center justify-center">
                    @if($product->primary_image)
                        <img src="{{ asset('storage/' . $product->primary_image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover rounded-lg" loading="lazy">
                    @else
                        <i class="fa-solid fa-image text-2xl text-[var(--color-line)]"></i>
                    @endif
                </div>
                <div>
                    <h3 class="font-medium text-[var(--color-ink)]">{{ $product->name }}</h3>
                    <p class="text-sm text-[var(--color-steel)]"> {{ format_currency($product->sale_price ?? $product->base_price) }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg border hairline p-6">
        <form method="POST" action="{{ route('customer.reviews.store') }}" x-data="{ rating: {{ old('rating', 5) }} }">
            @csrf
            <input type="hidden" name="product_id" value="{{ request('product_id', $product->getKey() ?? '') }}">
            <input type="hidden" name="rating" :value="rating">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">Your Rating</label>
                    <div class="flex gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button" @click="rating = {{ $i }}" class="focus:outline-none">
                                <i class="fa-solid fa-star text-3xl text-[var(--color-line)]"></i>
                            </button>
                        @endfor
                    </div>
                    @error('rating')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="title" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Review Title</label>
                    <input type="text" name="title" id="title" value="{{ old('title') }}" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('title') border-[var(--color-red)] @enderror" placeholder="Summarize your experience">
                    @error('title')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="comment" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Your Review</label>
                    <textarea name="comment" id="comment" rows="5" class="w-full border hairline bg-white px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('comment') border-[var(--color-red)] @enderror" placeholder="Tell us about your experience with this product...">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <a href="{{ route('customer.reviews') }}" class="px-6 py-2.5 border hairline rounded-lg text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-[var(--color-red)] text-white rounded-lg hover:bg-[var(--color-red-dark)] transition">Submit Review</button>
            </div>
        </form>
    </div>
</div>
@endsection
