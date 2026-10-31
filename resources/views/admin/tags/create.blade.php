@extends('layouts.admin')

@section('title', 'Create Tag')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create Tag</h1>
    <a href="{{ route('admin.tags.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Tags</a>
</div>
@endsection

@section('content')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('admin.tags.store') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-6" id="tagForm">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name <span class="text-[var(--color-red)]">*</span></label>
            <input type="text"
                   name="name"
                   id="name"
                   value="{{ old('name') }}"
                   required
                   autocomplete="off"
                   class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('name') border-[var(--color-red)] @enderror"
                   placeholder="e.g., Summer Sale, Best Seller, New Arrival">
            @error('name')
                <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Slug preview --}}
        <div class="bg-[var(--color-paper-dim)]/50 border hairline rounded-lg p-4">
            <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">URL Slug (auto-generated)</label>
            <div class="flex items-center gap-2">
                <span class="px-3 py-2 bg-[var(--color-paper)] border hairline rounded text-[var(--color-steel)] text-sm font-mono">/tags/</span>
                <input type="text"
                       id="slugPreview"
                       value=""
                       readonly
                       class="flex-1 px-3 py-2 bg-[var(--color-paper)] border hairline rounded text-[var(--color-ink)] font-mono text-sm"
                       aria-label="Generated slug">
            </div>
            <p class="text-xs text-[var(--color-steel)] mt-2">The slug is derived from the name and used in URLs. It must be unique.</p>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-[var(--color-line)]">
            <a href="{{ route('admin.tags.index') }}" class="px-4 py-2 border hairline rounded-lg text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-paper)] transition">Cancel</a>
            <button type="submit" class="bg-[var(--color-red)] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Create Tag</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    const slugPreview = document.getElementById('slugPreview');

    function generateSlug(str) {
        return str
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    nameInput.addEventListener('input', function() {
        slugPreview.value = generateSlug(this.value);
    });

    // Set initial value if any
    if (nameInput.value) {
        slugPreview.value = generateSlug(nameInput.value);
    }
});
</script>
@endpush