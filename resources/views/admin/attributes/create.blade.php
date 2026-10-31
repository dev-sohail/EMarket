@extends('layouts.admin')

@section('title', 'Create Attribute')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Create Attribute</h1>
    <a href="{{ route('admin.attributes.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Attributes</a>
</div>
@endsection

@section('content')
<div class="max-w-3xl">
    <form method="POST" action="{{ route('admin.attributes.store') }}" class="bg-[var(--color-paper)] rounded-lg border hairline p-6 space-y-6" id="attributeForm">
        @csrf

        {{-- Basic Info --}}
        <div class="space-y-6 border-b border-[var(--color-line)] pb-6">
            <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Basic Information</h2>

            <div>
                <label for="name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name <span class="text-[var(--color-red)]">*</span></label>
                <input type="text"
                       name="name"
                       id="name"
                       value="{{ old('name') }}"
                       required
                       autocomplete="off"
                       class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('name') border-[var(--color-red)] @enderror"
                       placeholder="e.g., Color, Size, Material">
                @error('name')
                    <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Slug <span class="text-[var(--color-red)]">*</span></label>
                <input type="text"
                       name="slug"
                       id="slug"
                       value="{{ old('slug') }}"
                       required
                       class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('slug') border-[var(--color-red)] @enderror"
                       placeholder="color, size, material">
                <p class="mt-1 text-xs text-[var(--color-steel)]">Used in URLs and API. Lowercase, numbers, hyphens only.</p>
                @error('slug')
                    <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Type <span class="text-[var(--color-red)]">*</span></label>
                <select name="type" id="type" required class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('type') border-[var(--color-red)] @enderror">
                    <option value="">Select type</option>
                    @foreach(\App\Enums\AttributeType::options() as $type)
                        <option value="{{ $type['value'] }}" {{ old('type') === $type['value'] ? 'selected' : '' }}>{{ $type['label'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-[var(--color-steel)]" id="typeHelp"></p>
                @error('type')
                    <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="sort_order" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('sort_order') border-[var(--color-red)] @enderror">
                    @error('sort_order') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end gap-6 flex-wrap">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_filterable" value="1" {{ old('is_filterable') ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" id="is_filterable">
                        <span class="text-sm text-[var(--color-ink)]">Filterable in storefront</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_variant" value="1" {{ old('is_variant') ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]" id="is_variant">
                        <span class="text-sm text-[var(--color-ink)]">Used for product variants</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Values --}}
        <div class="space-y-4 pt-2">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-[var(--color-ink)]">Values</h2>
                <button type="button" data-action="addValue" class="text-[var(--color-red)] text-sm font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Add Value
                </button>
            </div>

            <div id="values-container" class="space-y-2">
                <div class="flex gap-2" data-removable>
                    <input type="text" name="values[]" class="flex-1 border hairline bg-[var(--color-paper)] px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]" placeholder="e.g. Red, Blue, Green">
                    <button type="button" data-action="remove-self" aria-label="Remove value" class="px-2 text-[var(--color-red)] text-sm">×</button>
                </div>
            </div>

            <p class="text-xs text-[var(--color-steel)]">Values are required for Select, Multi Select, and Color types.</p>
            @error('values') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
            @error('values.*') <p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-[var(--color-line)]">
            <a href="{{ route('admin.attributes.index') }}" class="px-4 py-2 border hairline rounded-lg text-sm font-medium text-[var(--color-ink-soft)] hover:bg-[var(--color-paper)] transition">Cancel</a>
            <button type="submit" class="bg-[var(--color-red)] text-white px-5 py-2.5 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">Create Attribute</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type');
    const typeHelp = document.getElementById('typeHelp');
    const isFilterable = document.getElementById('is_filterable');
    const isVariant = document.getElementById('is_variant');

    const typeDescriptions = {
        'text': 'Free text input. Used for specifications like "Material: Cotton".',
        'number': 'Numeric input. Used for specs like "Weight: 1.5 kg".',
        'select': 'Single choice from a list. Used for variants like "Size: S, M, L".',
        'multiselect': 'Multiple choices from a list. Used for specs like "Features: Waterproof, UV-resistant".',
        'color': 'Color picker. Used for variants like "Color: Red, Blue, Green".'
    };

    function updateTypeHelp() {
        const type = typeSelect.value;
        if (type && typeDescriptions[type]) {
            typeHelp.textContent = typeDescriptions[type];
            // Update checkbox hints
            const needsValues = ['select', 'multiselect', 'color'].includes(type);
            isFilterable.parentElement.style.opacity = '1';
            isVariant.parentElement.style.opacity = '1';
            if (!needsValues) {
                isVariant.checked = false;
                isVariant.parentElement.style.opacity = '0.5';
            }
        } else {
            typeHelp.textContent = '';
            isFilterable.parentElement.style.opacity = '1';
            isVariant.parentElement.style.opacity = '1';
        }
    }

    typeSelect.addEventListener('change', updateTypeHelp);
    updateTypeHelp();

    // Auto-generate slug from name
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    let slugManuallyEdited = false;

    slugInput.addEventListener('input', function() {
        slugManuallyEdited = true;
    });

    nameInput.addEventListener('input', function() {
        if (!slugManuallyEdited) {
            slugInput.value = this.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });

    // Add value row. The create screen never wired this up, so the "Add Value"
    // button rendered here did nothing at all; values are required for select,
    // multiselect and color attributes, so the form could not be completed.
    document.querySelector('[data-action="addValue"]').addEventListener('click', function() {
        const container = document.getElementById('values-container');
        const div = document.createElement('div');
        div.className = 'flex gap-2';
        div.setAttribute('data-removable', '');
        div.innerHTML = '<input type="text" name="values[]" class="flex-1 border hairline bg-[var(--color-paper)] px-3 py-2 text-sm focus:outline-none focus:border-[var(--color-ink)]" placeholder="e.g. Red, Blue, Green"><button type="button" data-action="remove-self" aria-label="Remove value" class="px-2 text-[var(--color-red)] text-sm">×</button>';
        container.appendChild(div);
    });
});
</script>
@endpush