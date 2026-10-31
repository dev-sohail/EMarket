@extends('layouts.admin')

@section('title', 'Create FAQ')

@section('header')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create FAQ</h1>
        <p class="text-[var(--color-steel)] mt-1">Add a question and answer pair to the storefront FAQ page</p>
    </div>
    <a href="{{ route('admin.faqs.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to FAQs
    </a>
</div>
@endsection

@section('content')
<div class="max-w-3xl space-y-6">
    <form method="POST" action="{{ route('admin.faqs.store') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-5">
        @csrf

        <div>
            <div class="flex items-baseline justify-between mb-1.5">
                <label for="question" class="block text-sm font-medium text-[var(--color-ink)]">Question <span class="text-[var(--color-red)]">*</span></label>
                <span class="text-xs text-[var(--color-steel)]">Max 255 characters</span>
            </div>
            <input type="text" name="question" id="question" value="{{ old('question') }}" maxlength="255" required
                   placeholder="e.g. How long does delivery take?"
                   class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20 @error('question') border-[var(--color-red)] @enderror">
            @error('question') <p class="text-[var(--color-red)] text-xs mt-1.5">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-baseline justify-between mb-1.5">
                <label for="answer" class="block text-sm font-medium text-[var(--color-ink)]">Answer <span class="text-[var(--color-red)]">*</span></label>
                <span class="text-xs text-[var(--color-steel)]">Max 5,000 characters</span>
            </div>
            <textarea name="answer" id="answer" rows="7" maxlength="5000" required
                      placeholder="Write a clear, direct answer customers can act on…"
                      class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20 @error('answer') border-[var(--color-red)] @enderror">{{ old('answer') }}</textarea>
            @error('answer') <p class="text-[var(--color-red)] text-xs mt-1.5">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label for="sort_order" class="block text-sm font-medium text-[var(--color-ink)] mb-1.5">Sort Order</label>
                <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                       class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 rounded-lg focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20 @error('sort_order') border-[var(--color-red)] @enderror">
                <p class="text-xs text-[var(--color-steel)] mt-1.5">Lower numbers appear first on the storefront</p>
                @error('sort_order') <p class="text-[var(--color-red)] text-xs mt-1.5">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <label for="is_active" class="flex items-center gap-3 cursor-pointer border hairline rounded-lg px-4 py-2.5 w-full hover:bg-[var(--color-paper-dim)]/50 transition">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                           class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    <span class="text-sm text-[var(--color-ink)]">Publish on storefront</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2 border-t border-[var(--color-line)]">
            <a href="{{ route('admin.faqs.index') }}" class="px-4 py-2 border hairline rounded-lg text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
            <button type="submit" class="bg-[var(--color-red)] text-white px-5 py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Create FAQ</button>
        </div>
    </form>

    <div class="bg-[var(--color-paper-dim)]/40 border hairline rounded-lg p-4">
        <p class="text-xs text-[var(--color-steel)] leading-relaxed">
            Only FAQs marked as published are shown to customers. You can change the visibility at any time from the list using the Publish/Hide action.
        </p>
    </div>
</div>
@endsection
