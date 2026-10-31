@php
    $isEdit = isset($definition) && $definition !== null;
    $isReflection = $isEdit && $definition->source === 'reflection';
    $isActive = (bool) old('is_active', $isEdit ? $definition->is_active : true);
    $endpointCount = $isEdit ? $definition->endpoints_count : 0;
@endphp

@csrf

<div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>
            <label for="name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name <span class="text-[var(--color-red)]">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $isEdit ? $definition->name : '') }}" required
                   class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('name') border-[var(--color-red)] @enderror">
            @error('name')
                <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="slug" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Slug <span class="text-[var(--color-red)]">*</span></label>

            @if ($isReflection)
                {{-- The slug is the key the reflector matches on: it derives it from the
                     model's table name and looks the definition up by it. Editing it here would
                     orphan this definition, and the next regeneration would silently create a
                     second one at the model's own slug. So it is shown, not editable. --}}
                <input type="text" id="slug" value="{{ $definition->slug }}" readonly
                       class="w-full border hairline bg-[var(--color-paper-dim)] px-4 py-2.5 font-mono text-[var(--color-steel)] cursor-not-allowed">
                <p class="mt-1 text-xs text-[var(--color-steel)]">
                    Derived from the model's table, and used to find this definition again on
                    regeneration. It cannot be changed.
                </p>
            @else
                <input type="text" name="slug" id="slug" value="{{ old('slug', $isEdit ? $definition->slug : '') }}" required
                       class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] font-mono @error('slug') border-[var(--color-red)] @enderror"
                       placeholder="e.g., storefront, categories">
                <p class="mt-1 text-xs text-[var(--color-steel)]">Unique machine identifier (lowercase letters, numbers, dashes, underscores).</p>
            @endif

            @error('slug')
                <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if ($isReflection)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>The name and description below belong to reflection.</strong>
            They are regenerated from the model, so editing them here is fine for now but the next
            regeneration will overwrite them. The generated endpoints — their paths, handlers and
            parameters — are replaced the same way. Change the model or the reflector, not the
            rows, if you want a different API shape.
        </div>
    @endif

    <div>
        <label for="description" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Description</label>
        <textarea name="description" id="description" rows="3"
                  class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] @error('description') border-[var(--color-red)] @enderror">{{ old('description', $isEdit ? $definition->description : '') }}</textarea>
        <p class="mt-1 text-xs text-[var(--color-steel)]">Human-readable description for administrators.</p>
        @error('description')
            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
        @enderror
    </div>

    <div class="bg-[var(--color-paper-dim)]/50 border hairline rounded-lg p-4">
        <h3 class="text-sm font-medium text-[var(--color-ink)] mb-2">Source</h3>

        @if ($isReflection)
            <p class="text-sm text-[var(--color-steel)]">
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800 mr-1">Reflection</span>
                This definition was generated from an Eloquent model. It is not a setting you can
                change here — regenerate it, or build a new manual definition alongside it.
            </p>
        @else
            <p class="text-sm text-[var(--color-steel)]">
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800 mr-1">Manual</span>
                Endpoints are defined one by one below, and you choose each route, handler and
                parameter.
            </p>
        @endif

        @if ($isReflection)
            <div class="mt-3 flex flex-wrap items-center gap-3">
                {{-- The parent update <form> wraps this partial on the edit screen,
                     so declaring a nested <form> here would be dropped by the HTML
                     parser and this button would submit the wrong action. It points
                     at the standalone form in edit.blade.php via `form` instead. --}}
                <button type="submit" form="definition-regenerate-form"
                        class="py-2 px-4 rounded-lg border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
                    Regenerate from model
                </button>
                <a href="{{ route('admin.api-management.definitions.reflect') }}"
                   class="py-2 px-4 rounded-lg border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
                    Reflect a different model
                </a>
            </div>
            <p class="mt-2 text-xs text-[var(--color-steel)]">
                Regeneration is the maintenance step: run it after a migration changes the model's
                table and the filters and endpoints pick the change up.
            </p>
        @else
            <p class="mt-3 text-xs text-[var(--color-steel)]">
                To build an API straight from a model's schema instead, use
                <a href="{{ route('admin.api-management.definitions.reflect') }}" class="underline">Reflect API from Model</a>.
                It generates the definition, endpoints and filter parameters in one step.
            </p>
        @endif
    </div>

    @if($isEdit)
        <div class="pt-2 border-t border-[var(--color-line)]">
            <h3 class="text-sm font-medium text-[var(--color-ink)] mb-2">Status & Overview</h3>
            <div class="flex items-center gap-6 text-sm">
                <span class="text-[var(--color-steel)]">{{ $endpointCount }} endpoint{{ $endpointCount !== 1 ? 's' : '' }} defined</span>
            </div>
        </div>
    @endif

    <div class="pt-4 border-t border-[var(--color-line)]">
        <input type="hidden" name="is_active" value="0">
        <label for="is_active" class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_active" id="is_active" value="1" @checked($isActive)
                   class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
            <span class="text-sm text-[var(--color-ink-soft)]">Active <span class="text-xs text-[var(--color-steel)]">(will be available after route cache rebuild)</span></span>
        </label>
        @error('is_active')
            <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="flex items-center gap-3 mt-6 pt-4 border-t border-[var(--color-line)]">
    <button type="submit" class="bg-[var(--color-red)] text-white py-2.5 px-6 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition">
        {{ $isEdit ? 'Save Changes' : 'Create Definition' }}
    </button>
    <a href="{{ $isEdit ? route('admin.api-management.definitions.index') : route('admin.dashboard') }}" class="py-2.5 px-4 rounded-lg border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
        {{ $isEdit ? 'Cancel' : 'Back to Dashboard' }}
    </a>
</div>