@php
    $editing = isset($page) && $page;
    $titleValue = old('title', $page->title ?? '');
    $slugValue = old('slug', $page->slug ?? '');
    $contentValue = old('content', $page->content ?? '');
    $statusValue = old('status', $page->status ?? 'published');
    $metaTitleValue = old('meta_title', $page->meta_title ?? '');
    $metaDescriptionValue = old('meta_description', $page->meta_description ?? '');
    $fieldClass = 'w-full border hairline bg-[var(--color-paper)] rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-[var(--color-ink)] focus:ring-2 focus:ring-[var(--color-red)]/20';
@endphp

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
    {{-- Main column: content --}}
    <div class="xl:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline">
            <div class="px-6 py-4 border-b hairline">
                <h2 class="font-medium text-[var(--color-ink)]">Content</h2>
                <p class="text-xs text-[var(--color-steel)] mt-0.5">The title and body customers read on the storefront.</p>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label for="title" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1.5">Title <span class="text-[var(--color-red)]">*</span></label>
                    <input type="text"
                           name="title"
                           id="title"
                           value="{{ $titleValue }}"
                           required
                           maxlength="255"
                           autocomplete="off"
                           placeholder="e.g. About Us"
                           class="{{ $fieldClass }} @error('title') border-[var(--color-red)] @enderror">
                    @error('title')<p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="content" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1.5">Body <span class="text-[var(--color-red)]">*</span></label>
                    <textarea name="content"
                              id="content-editor"
                              rows="14"
                              required
                              maxlength="16000"
                              class="{{ $fieldClass }} @error('content') border-[var(--color-red)] @enderror">{{ $contentValue }}</textarea>
                    @error('content')<p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-[var(--color-steel)] mt-1.5">
                        Formatting is sanitised on save &mdash; scripts and inline styles are stripped.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline">
            <div class="px-6 py-4 border-b hairline">
                <h2 class="font-medium text-[var(--color-ink)]">Search engine listing</h2>
                <p class="text-xs text-[var(--color-steel)] mt-0.5">Optional. Falls back to the page title and body when left empty.</p>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label for="meta_title" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1.5">Meta title</label>
                    <input type="text"
                           name="meta_title"
                           id="meta_title"
                           value="{{ $metaTitleValue }}"
                           maxlength="255"
                           placeholder="{{ $titleValue ?: 'Shown as the blue link in search results' }}"
                           class="{{ $fieldClass }} @error('meta_title') border-[var(--color-red)] @enderror">
                    @error('meta_title')<p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="meta_description" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1.5">Meta description</label>
                    <textarea name="meta_description"
                              id="meta_description"
                              rows="3"
                              maxlength="500"
                              placeholder="Shown under the link in search results"
                              class="{{ $fieldClass }} @error('meta_description') border-[var(--color-red)] @enderror">{{ $metaDescriptionValue }}</textarea>
                    @error('meta_description')<p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-[var(--color-steel)] mt-1.5">Aim for 120&ndash;160 characters.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Sidebar: publishing controls --}}
    <div class="space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline">
            <div class="px-6 py-4 border-b hairline">
                <h2 class="font-medium text-[var(--color-ink)]">Visibility</h2>
            </div>
            <div class="p-6 space-y-3">
                <label class="block cursor-pointer">
                    <input type="radio" name="status" value="published" class="peer sr-only" @checked($statusValue === 'published')>
                    <span class="flex items-start gap-3 border hairline rounded-lg p-3.5 transition peer-checked:border-[#1B7A43] peer-checked:bg-[#1B7A43]/5 peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--color-red)]/40">
                        <span class="w-9 h-9 rounded-lg bg-[#1B7A43]/10 text-[#1B7A43] flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-globe"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-[var(--color-ink)]">Published</span>
                            <span class="block text-xs text-[var(--color-steel)] mt-0.5">Live for every visitor right away.</span>
                        </span>
                    </span>
                </label>

                <label class="block cursor-pointer">
                    <input type="radio" name="status" value="draft" class="peer sr-only" @checked($statusValue === 'draft')>
                    <span class="flex items-start gap-3 border hairline rounded-lg p-3.5 transition peer-checked:border-[var(--color-ink)] peer-checked:bg-[var(--color-paper-dim)] peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--color-red)]/40">
                        <span class="w-9 h-9 rounded-lg bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-pen-ruler"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-[var(--color-ink)]">Draft</span>
                            <span class="block text-xs text-[var(--color-steel)] mt-0.5">Hidden from the storefront until you publish.</span>
                        </span>
                    </span>
                </label>

                @error('status')<p class="text-[var(--color-red)] text-xs">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline">
            <div class="px-6 py-4 border-b hairline">
                <h2 class="font-medium text-[var(--color-ink)]">URL</h2>
            </div>
            <div class="p-6">
                <label for="slug" class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1.5">Slug <span class="text-[var(--color-red)]">*</span></label>
                <div class="flex items-stretch rounded-lg border hairline bg-[var(--color-paper)] focus-within:border-[var(--color-ink)] focus-within:ring-2 focus-within:ring-[var(--color-red)]/20 overflow-hidden @error('slug') border-[var(--color-red)] @enderror">
                    <span class="flex items-center px-3 text-sm text-[var(--color-steel)] bg-[var(--color-paper-dim)] border-r hairline font-mono">/</span>
                    <input type="text"
                           name="slug"
                           id="slug"
                           value="{{ $slugValue }}"
                           required
                           maxlength="255"
                           autocomplete="off"
                           placeholder="about-us"
                           data-slug-input
                           class="flex-1 min-w-0 bg-transparent border-0 px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-0">
                </div>
                @error('slug')<p class="text-[var(--color-red)] text-xs mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-[var(--color-steel)] mt-2">
                    {{ $editing
                        ? 'Changing the slug breaks existing links to this page.'
                        : 'Generated from the title while you type. Must be unique.' }}
                </p>
            </div>
        </div>

        @if($editing)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline">
                <div class="px-6 py-4 border-b hairline">
                    <h2 class="font-medium text-[var(--color-ink)]">Details</h2>
                </div>
                <dl class="p-6 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[var(--color-steel)]">ID</dt>
                        <dd class="font-mono text-xs text-[var(--color-ink-soft)]">{{ $page->getKey() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[var(--color-steel)]">Created</dt>
                        <dd class="text-[var(--color-ink-soft)]">{{ $page->created_at?->format('M d, Y H:i') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[var(--color-steel)]">Last updated</dt>
                        <dd class="text-[var(--color-ink-soft)]">{{ $page->updated_at?->diffForHumans() }}</dd>
                    </div>
                </dl>
                <div class="px-6 pb-6">
                    <a href="{{ route('page.show', $page->slug) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm font-medium text-[var(--color-red)] hover:text-[var(--color-red-dark)] transition">
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                        View on storefront
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
