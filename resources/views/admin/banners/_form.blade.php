@php
    $banner = $banner ?? null;
    $isEdit = $banner !== null;
    $imageValue = old('image', $banner?->image);
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="font-display font-semibold text-[var(--color-ink)]">Banner Information</h2>
                <p class="text-xs text-[var(--color-steel)]">Fields shown depend on the selected position.</p>
            </div>

            <div class="space-y-5">
                <div>
                    <label for="position-select" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Position <span class="text-[var(--color-red)]">*</span></label>
                    <select name="position" id="position-select" required class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)] @error('position') border-[var(--color-red)] @enderror">
                        @if(! $isEdit)
                            <option value="">Select position</option>
                        @endif
                        @foreach([
                            'homepage' => 'Homepage (Hero)',
                            'promotional' => 'Promotional (Mid-page)',
                            'sale' => 'Sale (Coloured Banner)',
                            'footer' => 'Footer (Image + Text)',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(old('position', $isEdit ? $banner->position : null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('position') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div id="field-subtitle" class="hidden">
                    <label for="subtitle-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Subtitle / Eyebrow</label>
                    <input type="text" id="subtitle-input" name="subtitle" value="{{ old('subtitle', $banner?->subtitle) }}" placeholder="New season, straight to your door" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                </div>

                <div id="field-title">
                    <label for="title-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Title <span class="title-required text-[var(--color-red)]">*</span></label>
                    <input type="text" id="title-input" name="title" value="{{ old('title', $banner?->title) }}" placeholder="Summer Sale" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)] @error('title') border-[var(--color-red)] @enderror">
                    @error('title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div id="field-description">
                    <label for="description-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Description</label>
                    <textarea id="description-input" name="description" rows="3" placeholder="Short supporting copy shown under the title." class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">{{ old('description', $banner?->description) }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div id="field-image">
                    <label for="image-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Image <span class="image-required text-[var(--color-red)]">*</span></label>
                    @if($isEdit && $banner->image)
                        <div class="mb-3">
                            <img src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}" class="w-48 h-28 object-cover rounded border hairline">
                        </div>
                    @endif
                    <input type="file" id="image-input" name="image" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[var(--color-paper-dim)] file:text-[var(--color-ink-soft)] hover:file:bg-[var(--color-line)]">
                    @error('image') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-[var(--color-steel)]">JPEG, PNG, JPG, GIF or WebP. Max 4MB.{{ $isEdit ? ' Leave empty to keep the current image.' : '' }}</p>
                    <div id="image-preview" class="mt-3 hidden">
                        <img src="" alt="Preview" class="w-48 h-28 object-cover rounded border hairline">
                    </div>
                </div>

                <div id="field-color" class="hidden">
                    <label for="color-hex" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Background Colour <span class="text-[var(--color-red)]">*</span> <span class="text-xs text-[var(--color-steel)]">(hex)</span></label>
                    <div class="flex items-center gap-3">
                        <input type="color" id="color-picker" value="{{ old('color', $banner?->color ?? '#D6001C') }}" class="w-12 h-10 rounded border hairline cursor-pointer flex-shrink-0">
                        <input type="text" name="color" id="color-hex" value="{{ old('color', $banner?->color ?? '#D6001C') }}" placeholder="#D6001C" maxlength="7" class="w-32 border hairline rounded-lg px-4 py-2.5 text-sm font-mono uppercase focus:ring-2 focus:ring-[var(--color-red)] @error('color') border-[var(--color-red)] @enderror">
                    </div>
                    @error('color') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    <div id="color-preview" class="mt-3 h-12 rounded-lg border hairline" style="background-color: {{ old('color', $banner?->color ?? '#D6001C') }}"></div>
                </div>

                <div id="field-url">
                    <label for="url-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Link URL</label>
                    <input type="url" id="url-input" name="url" value="{{ old('url', $banner?->url) }}" placeholder="https://example.com" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)] @error('url') border-[var(--color-red)] @enderror">
                    @error('url') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div id="field-cta">
                    <label for="cta-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">CTA Button Text</label>
                    <input type="text" id="cta-input" name="cta_text" value="{{ old('cta_text', $banner?->cta_text) }}" placeholder="Shop Now" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                </div>

                <div id="field-cta2" class="hidden">
                    <label for="cta2-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Second CTA Button Text</label>
                    <input type="text" id="cta2-input" name="cta_text_2" value="{{ old('cta_text_2', $banner?->cta_text_2) }}" placeholder="View Collections" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                </div>

                <div id="field-url2" class="hidden">
                    <label for="url2-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Second Button Link URL</label>
                    <input type="url" id="url2-input" name="url_2" value="{{ old('url_2', $banner?->url_2) }}" placeholder="https://example.com" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)] @error('url_2') border-[var(--color-red)] @enderror">
                    @error('url_2') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-4 lg:sticky lg:top-0">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <h2 class="font-display font-semibold text-[var(--color-ink)] mb-4">Settings</h2>
            <div class="space-y-4">
                <div id="field-sort">
                    <label for="sort-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Sort Order</label>
                    <input type="number" id="sort-input" name="sort_order" value="{{ old('sort_order', $banner?->sort_order ?? 0) }}" min="0" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                </div>
                <div id="field-schedule">
                    <label for="starts-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Starts At</label>
                    <input type="datetime-local" id="starts-input" name="starts_at" value="{{ old('starts_at', $banner?->starts_at?->format('Y-m-d\TH:i')) }}" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                </div>
                <div id="field-schedule-end">
                    <label for="ends-input" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Ends At</label>
                    <input type="datetime-local" id="ends-input" name="ends_at" value="{{ old('ends_at', $banner?->ends_at?->format('Y-m-d\TH:i')) }}" class="w-full border hairline rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                </div>
                <div class="pt-3 border-t hairline">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $banner?->is_active ?? true)) class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                        <span class="text-sm text-[var(--color-ink-soft)]">Active</span>
                    </label>
                </div>
            </div>
        </div>

        <div id="position-hint" class="bg-[var(--color-paper-dim)] border hairline rounded-lg p-4 hidden">
            <h3 class="flex items-center gap-2 text-sm font-medium text-[var(--color-ink)] mb-1">
                <i class="fa-solid fa-circle-info text-[var(--color-red)]"></i>
                <span id="hint-title"></span>
            </h3>
            <p class="text-xs text-[var(--color-steel)]" id="hint-desc"></p>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-4">
            <p class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider mb-3">Live Preview</p>
            <div id="live-preview" class="rounded-md overflow-hidden border hairline bg-[var(--color-paper-dim)]">
                <div id="live-preview-surface" class="px-4 py-5">
                    <p id="live-preview-subtitle" class="text-[10px] uppercase tracking-[0.14em] text-white/80 mb-1 hidden"></p>
                    <p id="live-preview-title" class="font-display text-base font-bold text-white leading-tight">Banner title</p>
                    <p id="live-preview-description" class="text-xs text-white/80 mt-1 hidden"></p>
                    <div id="live-preview-ctas" class="flex flex-wrap gap-2 mt-3"></div>
                </div>
            </div>
            <p class="text-[11px] text-[var(--color-steel)] mt-2">Approximate storefront rendering. Coloured sale banners use the chosen background; others fall back to the image or a neutral tone.</p>
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="w-full bg-[var(--color-red)] text-white py-3 rounded-lg text-sm font-semibold hover:bg-[var(--color-red-dark)] transition">{{ $submitLabel ?? ($isEdit ? 'Update Banner' : 'Create Banner') }}</button>
            <a href="{{ route('admin.banners.index') }}" class="w-full text-center py-2.5 rounded-lg border hairline text-sm text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)] transition">Cancel</a>
        </div>
    </div>
</div>
