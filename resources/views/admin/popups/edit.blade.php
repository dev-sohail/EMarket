@extends('layouts.admin')

@section('title', 'Edit Popup')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-[var(--color-ink)]">Edit Popup</h1>
    <a href="{{ route('admin.popups.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">Back to Popups</a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.popups.update', $popup) }}" enctype="multipart/form-data" id="popup-form" x-data="popupPreview()" @input.debounce.150ms="syncFromForm">
    @csrf
    @method('PUT')
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            {{-- Type Selector --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Type</h2>
                <div class="grid grid-cols-3 gap-3">
                    <label class="popup-type-card border rounded-lg p-4 text-center {{ $popup->type === 'image' ? 'is-selected' : '' }}">
                        <input type="radio" name="type" value="image" {{ $popup->type === 'image' ? 'checked' : '' }} class="sr-only" x-model="type">
                        <svg class="w-8 h-8 mx-auto mb-2 text-[var(--color-red)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v10z"/></svg>
                        <span class="text-sm font-medium text-[var(--color-ink)]">Image</span>
                    </label>
                    <label class="popup-type-card border rounded-lg p-4 text-center {{ $popup->type === 'solid_color' ? 'is-selected' : '' }}">
                        <input type="radio" name="type" value="solid_color" {{ $popup->type === 'solid_color' ? 'checked' : '' }} class="sr-only" x-model="type">
                        <svg class="w-8 h-8 mx-auto mb-2 text-[var(--color-red)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                        <span class="text-sm font-medium text-[var(--color-ink)]">Solid Color</span>
                    </label>
                    <label class="popup-type-card border rounded-lg p-4 text-center {{ $popup->type === 'custom_html' ? 'is-selected' : '' }}">
                        <input type="radio" name="type" value="custom_html" {{ $popup->type === 'custom_html' ? 'checked' : '' }} class="sr-only" x-model="type">
                        <svg class="w-8 h-8 mx-auto mb-2 text-[var(--color-red)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        <span class="text-sm font-medium text-[var(--color-ink)]">Custom HTML</span>
                    </label>
                </div>
                @error('type') <p class="mt-2 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>

            {{-- Content --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Content</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Name *</label>
                        <input type="text" name="name" value="{{ old('name', $popup->name) }}" placeholder="e.g. Summer Sale Popup" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('name') border-[var(--color-red)] @enderror" x-model="name">
                        @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Title</label>
                        <input type="text" name="title" value="{{ old('title', $popup->title) }}" placeholder="Popup title" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('title') border-[var(--color-red)] @enderror" x-model="title">
                        @error('title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Subtitle / Eyebrow</label>
                        <input type="text" name="subtitle" value="{{ old('subtitle', $popup->subtitle) }}" placeholder="New season, straight to your door" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500" x-model="subtitle">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Description</label>
                        <textarea name="description" rows="3" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500" x-model="description">{{ old('description', $popup->description) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Type-specific fields --}}
            <div id="field-color" class="{{ in_array(old('type', $popup->type), ['solid_color', 'custom_html']) ? '' : 'hidden' }} bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Background Color</h2>
                <div class="flex items-center gap-3">
                    <input type="color" id="color-picker" value="{{ old('color', $popup->color ?? '#D6001C') }}" class="w-12 h-10 rounded border cursor-pointer" @input="color = $event.target.value">
                    <input type="text" name="color" id="color-hex" value="{{ old('color', $popup->color ?? '#D6001C') }}" placeholder="#D6001C" maxlength="7" class="w-32 border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('color') border-[var(--color-red)] @enderror" x-model="color">
                    @error('color') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <div id="color-preview" class="mt-3 h-12 rounded-lg" :style="{ backgroundColor: color }"></div>
            </div>

            <div id="field-overlay-color" class="{{ in_array(old('type', $popup->type), ['solid_color', 'custom_html', 'image']) ? '' : 'hidden' }} bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Overlay Color</h2>
                <div class="flex items-center gap-3">
                    <input type="color" id="overlay-color-picker" value="{{ old('overlay_color', $popup->overlay_color ?? '#000000') }}" class="w-12 h-10 rounded border cursor-pointer" @input="overlayColor = $event.target.value">
                    <input type="text" name="overlay_color" id="overlay-color-hex" value="{{ old('overlay_color', $popup->overlay_color ?? '#000000') }}" placeholder="#000000" maxlength="7" class="w-32 border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('overlay_color') border-[var(--color-red)] @enderror" x-model="overlayColor">
                    @error('overlay_color') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <p class="mt-1 text-xs text-[var(--color-steel)]">Color of the dark overlay behind the popup.</p>
            </div>

            <div id="field-image" class="{{ old('type', $popup->type) === 'image' ? '' : 'hidden' }} bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Image</h2>
                <div class="mb-3">
                    @if($popup->image)
                        <img id="image-preview-img" src="{{ asset('storage/' . $popup->image) }}" alt="Preview" class="w-40 h-24 object-cover rounded border">
                    @else
                        <img id="image-preview-img" src="" alt="Preview" class="w-40 h-24 object-cover rounded border hidden">
                    @endif
                </div>
                <input type="file" name="image" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('image') border-[var(--color-red)] @enderror file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[var(--color-paper-dim)] file:text-[var(--color-red-dark)] hover:file:bg-[var(--color-paper-dim)]" @change="handleImagePreview" x-model="imageFile">
                @error('image') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-[var(--color-steel)]">JPEG, PNG, JPG, GIF, or WebP. Max 4MB. Leave empty to keep current image.</p>
            </div>

            <div id="field-html" class="{{ old('type', $popup->type) === 'custom_html' ? '' : 'hidden' }} bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Custom HTML</h2>
                <textarea name="custom_html" rows="8" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('custom_html') border-[var(--color-red)] @enderror" placeholder="<div class='text-center'>Your HTML content here</div>" x-model="customHtml">{{ old('custom_html', $popup->custom_html) }}</textarea>
                @error('custom_html') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-[var(--color-steel)]">Enter valid HTML for the popup content.</p>
            </div>

            {{-- Link URL & CTA --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Call to Action</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Link URL</label>
                        <input type="url" name="url" value="{{ old('url', $popup->url) }}" placeholder="https://example.com" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('url') border-[var(--color-red)] @enderror" x-model="url">
                        @error('url') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">CTA Button Text</label>
                        <input type="text" name="cta_text" value="{{ old('cta_text', $popup->cta_text) }}" placeholder="Shop Now" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500" x-model="ctaText">
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            {{-- Settings --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Settings</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Position *</label>
                        <select name="position" required class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500 @error('position') border-[var(--color-red)] @enderror">
                            <option value="">Select position</option>
                            <option value="homepage" {{ old('position', $popup->position) == 'homepage' ? 'selected' : '' }}>Homepage</option>
                            <option value="all_pages" {{ old('position', $popup->position) == 'all_pages' ? 'selected' : '' }}>All Pages</option>
                            <option value="product_page" {{ old('position', $popup->position) == 'product_page' ? 'selected' : '' }}>Product Page</option>
                            <option value="category_page" {{ old('position', $popup->position) == 'category_page' ? 'selected' : '' }}>Category Page</option>
                            <option value="cart" {{ old('position', $popup->position) == 'cart' ? 'selected' : '' }}>Cart Page</option>
                            <option value="checkout" {{ old('position', $popup->position) == 'checkout' ? 'selected' : '' }}>Checkout Page</option>
                        </select>
                        @error('position') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Size *</label>
                        <div class="flex gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="size" value="small" {{ old('size', $popup->size) == 'small' ? 'checked' : '' }} class="text-[var(--color-red)] focus:ring-indigo-500" x-model="size">
                                <span class="text-sm text-[var(--color-ink-soft)]">Small</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="size" value="medium" {{ old('size', $popup->size) == 'medium' ? 'checked' : '' }} class="text-[var(--color-red)] focus:ring-indigo-500" x-model="size">
                                <span class="text-sm text-[var(--color-ink-soft)]">Medium</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="size" value="large" {{ old('size', $popup->size) == 'large' ? 'checked' : '' }} class="text-[var(--color-red)] focus:ring-indigo-500" x-model="size">
                                <span class="text-sm text-[var(--color-ink-soft)]">Large</span>
                            </label>
                        </div>
                        @error('size') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $popup->sort_order) }}" min="0" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Starts At</label>
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $popup->starts_at?->format('Y-m-d\TH:i')) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Ends At</label>
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $popup->ends_at?->format('Y-m-d\TH:i')) }}" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Delay (seconds)</label>
                        <input type="number" name="delay_seconds" value="{{ old('delay_seconds', $popup->delay_seconds) }}" min="0" max="3600" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-[var(--color-steel)]">Wait before showing the popup.</p>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $popup->is_active) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                            <span class="text-sm text-[var(--color-ink-soft)]">Active</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="show_close_button" value="0">
                            <input type="checkbox" name="show_close_button" value="1" {{ old('show_close_button', $popup->show_close_button) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                            <span class="text-sm text-[var(--color-ink-soft)]">Show Close Button</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="enable_cookie" value="0">
                            <input type="checkbox" name="enable_cookie" value="1" {{ old('enable_cookie', $popup->enable_cookie) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                            <span class="text-sm text-[var(--color-ink-soft)]">Remember Close (Enable Cookie)</span>
                        </label>
                        <p class="mt-1 text-xs text-[var(--color-steel)]">Don't show again after close for a period.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Cookie Expiry (days)</label>
                        <input type="number" name="cookie_expiry_days" value="{{ old('cookie_expiry_days', $popup->cookie_expiry_days) }}" min="1" max="365" class="w-full border rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-[var(--color-steel)]">Days before popup can reappear after close.</p>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="show_only_once" value="0">
                            <input type="checkbox" name="show_only_once" value="1" {{ old('show_only_once', $popup->show_only_once) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                            <span class="text-sm text-[var(--color-ink-soft)]">Show Only Once Per Session</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="show_on_mobile" value="0">
                            <input type="checkbox" name="show_on_mobile" value="1" {{ old('show_on_mobile', $popup->show_on_mobile) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                            <span class="text-sm text-[var(--color-ink-soft)]">Show on Mobile</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="show_on_desktop" value="0">
                            <input type="checkbox" name="show_on_desktop" value="1" {{ old('show_on_desktop', $popup->show_on_desktop) ? 'checked' : '' }} class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-indigo-500">
                            <span class="text-sm text-[var(--color-ink-soft)]">Show on Desktop</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Live Preview --}}
            <div class="bg-[var(--color-paper)] rounded-lg border p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-4">Live Preview</h2>
                <div class="popup-preview-overlay rounded-lg overflow-hidden border bg-black/20 p-4 flex items-center justify-center" :style="{ backgroundColor: overlayColor }">
                    <div class="w-full rounded-lg shadow-xl popup-preview-panel" :style="{ backgroundColor: color }">
                        <div class="p-5" x-show="type !== 'image' && type !== 'custom_html'">
                            <span class="eyebrow text-xs text-[var(--color-red)]" x-show="subtitle" x-text="subtitle"></span>
                            <h3 class="font-display text-xl font-bold text-[var(--color-ink)] mt-1" x-show="title" x-text="title"></h3>
                            <p class="text-sm text-[var(--color-steel)] mt-2" x-show="description" x-text="description"></p>
                        </div>
                        <img x-show="type === 'image' && (imagePreview || $popup?.image)" :src="imagePreview || '{{ $popup->image ? asset('storage/' . $popup->image) : '' }}'" class="w-full h-32 object-cover" alt="Preview">
                        <div x-show="type === 'custom_html'" class="p-5" x-ref="customHtmlOutput" x-effect="renderCustomHtml()"></div>
                        @if($popup->cta_text ?? false)
                            <div class="p-4 border-t hairline flex justify-end">
                                <span class="btn btn-primary text-sm">{{ $popup->cta_text }}</span>
                            </div>
                        @endif
                    </div>
                </div>
                <p class="mt-2 text-xs text-[var(--color-steel)]">This is a simplified preview. The actual popup may vary based on size and position settings.</p>
            </div>

            <button type="submit" class="w-full bg-[var(--color-red)] text-white py-3 rounded-lg font-semibold hover:bg-[var(--color-red-dark)] transition">Update Popup</button>
        </div>
    </div>
</form>

@push('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('alpine:init', function () {
    window.Alpine.data('popupPreview', function () {
        return {
            type: '{{ old('type', $popup->type) }}',
            subtitle: '{{ old('subtitle', $popup->subtitle) }}',
            title: '{{ old('title', $popup->title) }}',
            description: '{{ old('description', $popup->description) }}',
            color: '{{ old('color', $popup->color ?? '#D6001C') }}',
            overlayColor: '{{ old('overlay_color', $popup->overlay_color ?? '#000000') }}',
            imagePreview: '',
            customHtml: '{{ old('custom_html', $popup->custom_html ?? '') }}',
            ctaText: '{{ old('cta_text', $popup->cta_text) }}',
            url: '{{ old('url', $popup->url) }}',
            name: '{{ old('name', $popup->name) }}',
            size: '{{ old('size', $popup->size) }}',

            init() {
                this.$watch('color', (val) => {
                    const hexInput = document.getElementById('color-hex');
                    const preview = document.getElementById('color-preview');
                    const picker = document.getElementById('color-picker');
                    if (hexInput && hexInput.value !== val) hexInput.value = val;
                    if (preview) preview.style.backgroundColor = val;
                    if (picker && picker.value !== val) picker.value = val;
                });
                this.$watch('overlayColor', (val) => {
                    const hexInput = document.getElementById('overlay-color-hex');
                    const picker = document.getElementById('overlay-color-picker');
                    if (hexInput && hexInput.value !== val) hexInput.value = val;
                    if (picker && picker.value !== val) picker.value = val;
                });

                // Initialize image preview if exists
                if ('{{ $popup->image ?? '' }}') {
                    this.imagePreview = '{{ $popup->image ? asset('storage/' . $popup->image) : '' }}';
                }
            },

            renderCustomHtml() {
                // The CSP build of Alpine forbids x-html, so the preview is
                // written imperatively. custom_html is authored by admins.
                const el = this.$refs.customHtmlOutput;
                if (!el) return;
                el.innerHTML = (this.type === 'custom_html' && this.customHtml)
                    ? this.customHtml
                    : '<span class="text-gray-400">HTML content</span>';
            },

            handleImagePreview(event) {
                const file = event.target.files[0];
                const preview = document.getElementById('image-preview-img');
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        this.imagePreview = ev.target.result;
                        if (preview) {
                            preview.src = ev.target.result;
                            preview.classList.remove('hidden');
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    this.imagePreview = '';
                    if (preview) preview.classList.add('hidden');
                }
            },

            syncFromForm() {
                // Alpine x-model keeps these in sync
            }
        };
    });
});

// Type field toggling
(function() {
    const typeFields = {
        image: { subtitle: true, title: true, description: true, color: false, overlay: true, image: true, html: false, url: true, cta: true },
        solid_color: { subtitle: true, title: true, description: true, color: true, overlay: true, image: false, html: false, url: true, cta: true },
        custom_html: { subtitle: false, title: false, description: false, color: true, overlay: true, image: false, html: true, url: true, cta: true },
    };

    function applyType(t) {
        const f = typeFields[t];
        if (!f) return;
        Object.entries(f).forEach(([key, show]) => {
            const el = document.getElementById('field-' + key);
            if (el) el.classList.toggle('hidden', !show);
        });
    }

    // Wire up radio buttons
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            applyType(this.value);
            document.querySelectorAll('.popup-type-card').forEach(card => {
                card.classList.toggle('is-selected', card.querySelector('input')?.value === this.value);
            });
        });
    });

    // Set initial state
    const initialType = document.querySelector('input[name="type"]:checked')?.value || '';
    if (initialType) applyType(initialType);
})();
</script>
@endpush

@endsection
