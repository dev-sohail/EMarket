{{--
    Shared body of the category create and edit forms.

    `$category` is null on create, which is what selects the `old()` fallback
    for every field. `getKey()` is used rather than `->id`: categories are
    keyed by the prefixed `_id` and the `id` alias is an accessor that is not
    part of toArray().
--}}
@php
    $isEdit = $category !== null;
    $selectedParent = old('parent_id', $isEdit ? $category->parent_id : null);
@endphp

{{-- No error summary here: layouts.admin already renders every validation
     message through components/admin/alerts, so a local copy duplicated the
     banner. --}}

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-folder-open text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Category Information</h2>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name <span class="text-[var(--color-red)]">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $category->name ?? '') }}" required maxlength="255"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('name') border-[var(--color-red)] @enderror"
                           placeholder="Kitchen">
                    @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug', $category->slug ?? '') }}" maxlength="255"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 font-mono focus:ring-2 focus:ring-[var(--color-red)] @error('slug') border-[var(--color-red)] @enderror"
                           placeholder="Auto-generated from the name if left empty">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">This is the storefront URL segment.</p>
                    @error('slug') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Description</label>
                    <textarea id="description" name="description" rows="6"
                              class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('description') border-[var(--color-red)] @enderror">{{ old('description', $category->description ?? '') }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-magnifying-glass-chart text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">SEO</h2>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="meta_title" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Meta Title</label>
                    <input type="text" id="meta_title" name="meta_title" value="{{ old('meta_title', $category->meta_title ?? '') }}" maxlength="255"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('meta_title') border-[var(--color-red)] @enderror"
                           placeholder="{{ old('name', $category->name ?? 'Category name') }}">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Falls back to the category name.</p>
                    @error('meta_title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="meta_description" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Meta Description</label>
                    <textarea id="meta_description" name="meta_description" rows="3" maxlength="500"
                              class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('meta_description') border-[var(--color-red)] @enderror">{{ old('meta_description', $category->meta_description ?? '') }}</textarea>
                    @error('meta_description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-gear text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Settings</h2>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="parent_id" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Parent Category</label>
                    <select id="parent_id" name="parent_id" class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)]">
                        <option value="">None (Top Level)</option>
                        @foreach($parentCategories as $parent)
                            <option value="{{ $parent->getKey() }}" @selected($selectedParent === $parent->getKey())>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-[var(--color-steel)]">
                        @if($isEdit)
                            Nesting under an active category is limited to one level, so a subcategory cannot itself gain children.
                        @else
                            Top-level categories drive the main navigation.
                        @endif
                    </p>
                </div>

                <div>
                    <label for="image" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Image</label>
                    @if($isEdit && $category->image)
                        <img src="{{ asset('storage/' . $category->image) }}" alt="" class="w-20 h-20 object-cover rounded-lg mb-2 border hairline">
                    @endif
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 file:mr-3 file:py-1 file:px-3 file:border-0 file:rounded file:bg-[var(--color-paper-dim)] file:text-sm">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">
                        {{ $isEdit ? 'Leave empty to keep the current image.' : 'Shown on the category page and in listings.' }}
                    </p>
                    @error('image') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="sort_order" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Sort Order</label>
                    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('sort_order') border-[var(--color-red)] @enderror">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Lower numbers appear first in the catalogue.</p>
                    @error('sort_order') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                {{-- The paired hidden field is what makes an unchecked box
                     submit 0 instead of omitting the key, so switching a
                     category off cannot silently re-enable it on the next
                     unrelated save. --}}
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $isEdit ? $category->is_active : true)) class="mt-0.5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    <span>
                        <span class="block text-sm text-[var(--color-ink)]">Active</span>
                        <span class="block text-xs text-[var(--color-steel)]">Inactive categories are hidden from the storefront but stay listed here.</span>
                    </span>
                </label>

                {{-- Only top-level categories are rendered in the home page grid,
                     so this has no effect on a category with a parent. --}}
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="hidden" name="show_on_home" value="0">
                    <input type="checkbox" name="show_on_home" value="1" @checked(old('show_on_home', $isEdit ? $category->show_on_home : true)) class="mt-0.5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    <span>
                        <span class="block text-sm text-[var(--color-ink)]">Show on home page</span>
                        <span class="block text-xs text-[var(--color-steel)]">Top-level categories ticked here appear in the storefront home page grid. Unticking keeps the category in the catalogue and navigation.</span>
                    </span>
                </label>
            </div>
        </div>

        @if($isEdit)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1">Details</h2>
                <dl class="space-y-2 text-sm mt-3">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[var(--color-steel)]">Products</dt>
                        <dd class="font-medium text-[var(--color-ink)]">{{ $category->products()->count() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[var(--color-steel)]">Subcategories</dt>
                        <dd class="font-medium text-[var(--color-ink)]">{{ $category->children()->count() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-[var(--color-steel)]">Created</dt>
                        <dd class="font-medium text-[var(--color-ink)]">{{ $category->created_at?->format('j M Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        @endif
    </div>
</div>