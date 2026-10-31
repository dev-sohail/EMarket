{{--
    Shared body of the product create and edit forms.

    The two screens differ only in the values they pre-fill, the submit label
    and the images block, so the markup lives here once. `$product` is null on
    create, which is what selects the `old()` fallback for every field.

    Two conventions this partial depends on:

    - `$attributes` and `$attribute_values` are keyed by the prefixed `_id`,
      never by an `id` column, so every reference reads `getKey()`.
    - A variant is addressed the same way. `ProductVariant::toArray()` does not
      emit `id` (the alias is an accessor, not an appended attribute), so the
      row's identity is read from `_id`. Reading `$variant['id']` yields null,
      the service then treats the row as new, and the insert collides on
      `combination_key`.
--}}
@php
    $isEdit = $product !== null;
    $existingImageCount = $isEdit ? $product->images->count() : 0;
    $variantAttributes = $attributes->where('is_variant', true);

    // Variant rows come from three possible sources, in priority order: the
    // submission that failed validation, the stored variants, or nothing.
    $variantRows = old('variants');

    if ($variantRows === null) {
        $variantRows = $isEdit
            ? $product->variants->map(fn ($variant) => [
                'id' => $variant->getKey(),
                'name' => $variant->name,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'stock' => $variant->stock_quantity,
                'price_add_to_main' => (bool) $variant->price_add_to_main,
                'is_active' => (bool) $variant->is_active,
                'attribute_values' => $variant->relationLoaded('attributeValues')
                    ? $variant->attributeValues->pluck('_id')->all()
                    : [],
            ])->all()
            : [];
    }
@endphp

{{-- No error summary here: layouts.admin already renders every validation
     message through components/admin/alerts. A local copy of that banner showed
     the same "There is 1 problem with this form" twice, once as a plain list and
     once bulleted. Field-level @error(...) messages below are the only ones that
     belong to this partial. --}}

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    {{-- ------------------------------------------------------------------
         Main column
    ------------------------------------------------------------------ --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-circle-info text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Basic Information</h2>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Product Name <span class="text-[var(--color-red)]">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $product->name ?? '') }}" required maxlength="255"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('name') border-[var(--color-red)] @enderror"
                           placeholder="Cast Iron Skillet">
                    @error('name') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="slug" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Slug <span class="text-[var(--color-red)]">*</span></label>
                        <input type="text" id="slug" name="slug" value="{{ old('slug', $product->slug ?? '') }}" required maxlength="255"
                               class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('slug') border-[var(--color-red)] @enderror"
                               placeholder="cast-iron-skillet">
                        @error('slug') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="sku" class="block text-sm font-medium text-[var(--color-ink)] mb-1">SKU <span class="text-[var(--color-red)]">*</span></label>
                        <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku ?? '') }}" required maxlength="100"
                               class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 font-mono focus:ring-2 focus:ring-[var(--color-red)] @error('sku') border-[var(--color-red)] @enderror"
                               placeholder="SKU-001">
                        @error('sku') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="short_description" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Short Description</label>
                    <textarea id="short_description" name="short_description" rows="2" maxlength="500"
                              class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('short_description') border-[var(--color-red)] @enderror">{{ old('short_description', $product->short_description ?? '') }}</textarea>
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Shown under the product title on the product page.</p>
                    @error('short_description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Description <span class="text-[var(--color-red)]">*</span></label>
                    <textarea id="description" name="description" rows="8"
                              class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('description') border-[var(--color-red)] @enderror">{{ old('description', $product->description ?? '') }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-tag text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Pricing</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="base_price" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Base Price <span class="text-[var(--color-red)]">*</span></label>
                    <input type="number" id="base_price" name="base_price" value="{{ old('base_price', $product->base_price ?? '') }}" step="0.01" min="0" required
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 price focus:ring-2 focus:ring-[var(--color-red)] @error('base_price') border-[var(--color-red)] @enderror">
                    @error('base_price') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sale_price" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Sale Price</label>
                    <input type="number" id="sale_price" name="sale_price" value="{{ old('sale_price', $product->sale_price ?? '') }}" step="0.01" min="0"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 price focus:ring-2 focus:ring-[var(--color-red)] @error('sale_price') border-[var(--color-red)] @enderror">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Must be below the base price.</p>
                    @error('sale_price') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="discount_type" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Discount Type</label>
                    <select id="discount_type" name="discount_type" class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('discount_type') border-[var(--color-red)] @enderror">
                        @foreach(['none' => 'No Discount', 'percent' => 'Percentage', 'fixed' => 'Fixed Amount'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('discount_type', $product->discount_type ?? 'none') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('discount_type') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="discount_value" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Discount Value</label>
                    <input type="number" id="discount_value" name="discount_value" value="{{ old('discount_value', $product->discount_value ?? '') }}" step="0.01" min="0"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 price focus:ring-2 focus:ring-[var(--color-red)] @error('discount_value') border-[var(--color-red)] @enderror">
                    @error('discount_value') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="cost_price" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Cost Price</label>
                    <input type="number" id="cost_price" name="cost_price" value="{{ old('cost_price', $product->cost_price ?? '') }}" step="0.01" min="0"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 price focus:ring-2 focus:ring-[var(--color-red)] @error('cost_price') border-[var(--color-red)] @enderror">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Hidden from the storefront; used for margin reporting.</p>
                    @error('cost_price') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-ruler-combined text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Dimensions &amp; Shipping</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach([
                    'weight' => 'Weight (kg)',
                    'length' => 'Length (cm)',
                    'width' => 'Width (cm)',
                    'height' => 'Height (cm)',
                ] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium text-[var(--color-ink)] mb-1">{{ $label }}</label>
                        <input type="number" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $product->{$field} ?? '') }}" step="0.01" min="0"
                               class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error($field) border-[var(--color-red)] @enderror">
                        @error($field) <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-sliders text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Attributes</h2>
            </div>

            @if($attributes->isEmpty())
                <p class="text-sm text-[var(--color-steel)]">No attributes are defined yet.</p>
            @else
                <div class="space-y-5">
                    @foreach($attributes as $attribute)
                        @php
                            // `attributes` and `attribute_values` are keyed by the
                            // prefixed `_id`; there is no `id` column on either
                            // table, so the row is addressed by its primary key.
                            $attributeKey = $attribute->getKey();
                            $attributeIndex = $loop->index;
                            $selectedValues = [];
                            foreach (old('attributes', []) as $attr) {
                                if (($attr['attribute_id'] ?? null) === $attributeKey) {
                                    $selectedValues = $attr['values'] ?? [];
                                    break;
                                }
                            }
                            if (empty($selectedValues) && $isEdit) {
                                $selectedValues = $product->attributeValues
                                    ->where('pivot.attribute_id', $attributeKey)
                                    ->pluck('_id')
                                    ->toArray();
                            }
                        @endphp
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-2">
                                {{ $attribute->name }}
                                <span class="text-xs font-normal text-[var(--color-steel)]">{{ \App\Enums\AttributeType::from($attribute->type)->label() }}</span>
                            </label>

                            @if($attribute->values->isEmpty())
                                {{-- No hidden attribute_id: submitting an entry with no
                                      values fails `attributes.*.values`, and an
                                      attribute with nothing to choose from should
                                      simply be omitted from the payload. --}}
                                <p class="text-xs text-[var(--color-steel)]">No values defined for this attribute yet.</p>
                            @else
                                <input type="hidden" name="attributes[{{ $attributeIndex }}][attribute_id]" value="{{ $attributeKey }}">
                                {{-- Only multiselect allows several values; the other
                                      types are single-choice, so they use radios. --}}
                                <div class="flex flex-wrap gap-2">
                                    @foreach($attribute->values as $value)
                                        @php $valueKey = $value->getKey(); @endphp
                                        <label class="inline-flex items-center gap-2 px-3 py-2 border hairline rounded-lg cursor-pointer hover:bg-[var(--color-paper-dim)] transition has-checked:border-[var(--color-red)]">
                                            <input type="{{ $attribute->type === \App\Enums\AttributeType::Multiselect->value ? 'checkbox' : 'radio' }}"
                                                   name="attributes[{{ $attributeIndex }}][values][]"
                                                   value="{{ $valueKey }}"
                                                   @checked(in_array($valueKey, $selectedValues))
                                                   class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                            @if($attribute->type === \App\Enums\AttributeType::Color->value)
                                                <span class="w-3 h-3 rounded-full border hairline" style="background-color: {{ $value->value }}"></span>
                                            @endif
                                            <span class="text-sm">{{ $value->value }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @error('attributes.' . $attributeIndex . '.values')
                                <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if(! $isEdit)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-images text-[var(--color-red)] text-sm"></i>
                        <h2 class="font-semibold text-[var(--color-ink)]">Images</h2>
                    </div>
                    <span class="text-xs text-[var(--color-steel)]">At least one required</span>
                </div>
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp"
                       class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 file:mr-3 file:py-1 file:px-3 file:border-0 file:rounded file:bg-[var(--color-paper-dim)] file:text-sm @error('images') border-[var(--color-red)] @enderror">
                <p class="mt-1 text-xs text-[var(--color-steel)]">JPEG, PNG, JPG, GIF or WebP, up to 2 MB each. The first upload becomes the primary image.</p>
                @error('images') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                @error('images.*') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>
        @endif
    </div>

    {{-- ------------------------------------------------------------------
         Sidebar
    ------------------------------------------------------------------ --}}
    <div class="space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-toggle-on text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Visibility</h2>
            </div>
            <div class="space-y-3">
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="mt-0.5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    <span>
                        <span class="block text-sm text-[var(--color-ink)]">Active</span>
                        <span class="block text-xs text-[var(--color-steel)]">Visible to shoppers in the catalogue.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false)) class="mt-0.5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    <span>
                        <span class="block text-sm text-[var(--color-ink)]">Featured</span>
                        <span class="block text-xs text-[var(--color-steel)]">Promoted in featured sections and campaigns.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="is_new" value="1" @checked(old('is_new', $product->is_new ?? false)) class="mt-0.5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    <span>
                        <span class="block text-sm text-[var(--color-ink)]">New Arrival</span>
                        <span class="block text-xs text-[var(--color-steel)]">Badged as new on the storefront.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer">
                    <input type="checkbox" name="return_allowed" value="1" @checked(old('return_allowed', $product->return_allowed ?? true)) class="mt-0.5 rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                    <span>
                        <span class="block text-sm text-[var(--color-ink)]">Allow Returns</span>
                        <span class="block text-xs text-[var(--color-steel)]">Shoppers may raise a return for this product.</span>
                    </span>
                </label>
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-sitemap text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Organisation</h2>
            </div>
            <div class="space-y-4">
                @php
                    // Multi-selects rather than checkboxes: `required` on a
                    // multiple <select> still validates, and the payload shape
                    // (`category_ids[]`) is unchanged.
                    $selectedCategories = old('category_ids', $isEdit ? $product->categories->pluck('_id')->all() : []);
                    $selectedBrands = old('brand_ids', $isEdit ? $product->brands->pluck('_id')->all() : []);
                    $selectedTags = old('tags', $isEdit ? $product->tags->pluck('_id')->all() : []);
                @endphp

                <div>
                    <label for="category_ids" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Categories <span class="text-[var(--color-red)]">*</span></label>
                    <select id="category_ids" name="category_ids[]" multiple size="5"
                            class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('category_ids') border-[var(--color-red)] @enderror">
                        @foreach($categories ?? [] as $category)
                            <option value="{{ $category->getKey() }}" @selected(in_array($category->getKey(), $selectedCategories))>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Hold Ctrl/Cmd to select multiple.</p>
                    @error('category_ids') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="brand_ids" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Brands</label>
                    <select id="brand_ids" name="brand_ids[]" multiple size="5"
                            class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('brand_ids') border-[var(--color-red)] @enderror">
                        @foreach($brands ?? [] as $brand)
                            <option value="{{ $brand->getKey() }}" @selected(in_array($brand->getKey(), $selectedBrands))>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    @error('brand_ids') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="tags" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Tags</label>
                    <select id="tags" name="tags[]" multiple size="5"
                            class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('tags') border-[var(--color-red)] @enderror">
                        @foreach($tags ?? [] as $tag)
                            <option value="{{ $tag->getKey() }}" @selected(in_array($tag->getKey(), $selectedTags))>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                    @error('tags') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-boxes-stacked text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Inventory</h2>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="stock_status" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Stock Status <span class="text-[var(--color-red)]">*</span></label>
                    <select id="stock_status" name="stock_status" required
                            class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('stock_status') border-[var(--color-red)] @enderror">
                        @foreach(['in_stock' => 'In Stock', 'out_of_stock' => 'Out of Stock', 'on_backorder' => 'On Backorder'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('stock_status', $product?->stock_status?->value ?? 'in_stock') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('stock_status') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="stock_quantity" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Stock Quantity</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" min="0"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('stock_quantity') border-[var(--color-red)] @enderror">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Base stock. Each variant carries its own quantity below.</p>
                    @error('stock_quantity') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-layer-group text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Variants</h2>
            </div>

            @if($variantAttributes->isEmpty())
                <p class="text-xs text-[var(--color-steel)] mb-4">
                    No attributes are marked &ldquo;Used on variants&rdquo;, so this product has no variant combinations to choose from.
                </p>
            @endif

            <div id="variants-container" class="space-y-4">
                @foreach($variantRows as $index => $variant)
                    <div class="border hairline rounded-lg p-4" data-removable>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">
                                Variant {{ $loop->iteration }}
                                @if(! empty($variant['id']))
                                    <span class="normal-case tracking-normal text-[var(--color-steel)]">&middot; existing</span>
                                @endif
                            </p>
                            {{-- Inside the row it removes: `remove-self` walks up
                                 to the nearest [data-removable], so a button
                                 placed as a sibling of that wrapper did
                                 nothing at all. --}}
                            <button type="button" data-action="removeVariant" class="text-xs text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">
                                <i class="fa-solid fa-xmark mr-1"></i>Remove
                            </button>
                        </div>

                        <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] ?? '' }}">

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name</label>
                                <input type="text" name="variants[{{ $index }}][name]" value="{{ old('variants.' . $index . '.name', $variant['name'] ?? '') }}"
                                       class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)] @error('variants.' . $index . '.name') border-[var(--color-red)] @enderror">
                                @error('variants.' . $index . '.name') <p class="mt-1 text-xs text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">SKU</label>
                                <input type="text" name="variants[{{ $index }}][sku]" value="{{ old('variants.' . $index . '.sku', $variant['sku'] ?? '') }}"
                                       class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-[var(--color-red)] @error('variants.' . $index . '.sku') border-[var(--color-red)] @enderror">
                                @error('variants.' . $index . '.sku') <p class="mt-1 text-xs text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Price</label>
                                <input type="number" name="variants[{{ $index }}][price]" value="{{ old('variants.' . $index . '.price', $variant['price'] ?? '') }}" step="0.01" min="0"
                                       class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm price focus:ring-2 focus:ring-[var(--color-red)] @error('variants.' . $index . '.price') border-[var(--color-red)] @enderror">
                                @error('variants.' . $index . '.price') <p class="mt-1 text-xs text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Stock</label>
                                <input type="number" name="variants[{{ $index }}][stock]" value="{{ old('variants.' . $index . '.stock', $variant['stock'] ?? '') }}" min="0"
                                       class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)] @error('variants.' . $index . '.stock') border-[var(--color-red)] @enderror">
                                @error('variants.' . $index . '.stock') <p class="mt-1 text-xs text-[var(--color-red)]">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 mt-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="variants[{{ $index }}][price_add_to_main]" value="1"
                                       @checked(old('variants.' . $index . '.price_add_to_main', $variant['price_add_to_main'] ?? false))
                                       class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                <span class="text-xs text-[var(--color-ink)]">Adds to base price</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="variants[{{ $index }}][is_active]" value="1"
                                       @checked(old('variants.' . $index . '.is_active', $variant['is_active'] ?? true))
                                       class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                <span class="text-xs text-[var(--color-ink)]">Active</span>
                            </label>
                        </div>

                        @if($variantAttributes->isNotEmpty())
                            <div class="mt-4">
                                <p class="block text-sm font-medium text-[var(--color-ink)] mb-2">Attribute Combination</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($variantAttributes as $attribute)
                                        @foreach($attribute->values as $value)
                                            <label class="inline-flex items-center gap-2 px-2 py-1 border hairline rounded-lg cursor-pointer hover:bg-[var(--color-paper-dim)] transition text-xs">
                                                <input type="checkbox" name="variants[{{ $index }}][attribute_values][]" value="{{ $value->getKey() }}"
                                                       @checked(in_array($value->getKey(), $variant['attribute_values'] ?? []))
                                                       class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                                <span class="font-medium text-[var(--color-steel)]">{{ $attribute->name }}:</span>
                                                <span>{{ $value->value }}</span>
                                            </label>
                                        @endforeach
                                    @endforeach
                                </div>
                                @error('variants.' . $index . '.attribute_values')
                                    <p class="mt-1 text-xs text-[var(--color-red)]">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <p id="no-variants-msg" class="text-sm text-[var(--color-steel)] mt-3 {{ count($variantRows) > 0 ? 'hidden' : '' }}">
                No variants yet. Add one per attribute combination, such as a size or colour.
            </p>

            <button type="button" data-action="addVariant" class="mt-3 inline-flex items-center gap-2 px-3 py-2 border hairline rounded-lg text-sm font-medium text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition {{ $variantAttributes->isEmpty() ? 'opacity-50 cursor-not-allowed' : '' }}"
                    @disabled($variantAttributes->isEmpty())>
                <i class="fa-solid fa-plus text-xs"></i> Add Variant
            </button>

            {{-- The blank row a new variant is cloned from. Building it here
                 rather than as a JS string keeps the field names and classes in
                 one place, so a dynamically added row is identical to a
                 server-rendered one. --}}
            <template id="variant-row-template">
                <div class="border hairline rounded-lg p-4" data-removable>
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">New Variant</p>
                        <button type="button" data-action="removeVariant" class="text-xs text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">
                            <i class="fa-solid fa-xmark mr-1"></i>Remove
                        </button>
                    </div>

                    <input type="hidden" name="variants[__INDEX__][id]" value="">

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Name</label>
                            <input type="text" name="variants[__INDEX__][name]" class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">SKU</label>
                            <input type="text" name="variants[__INDEX__][sku]" class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-[var(--color-red)]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Price</label>
                            <input type="number" name="variants[__INDEX__][price]" step="0.01" min="0" class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm price focus:ring-2 focus:ring-[var(--color-red)]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--color-ink)] mb-1">Stock</label>
                            <input type="number" name="variants[__INDEX__][stock]" min="0" class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-red)]">
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 mt-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="variants[__INDEX__][price_add_to_main]" value="1" class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                            <span class="text-xs text-[var(--color-ink)]">Adds to base price</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="variants[__INDEX__][is_active]" value="1" checked class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                            <span class="text-xs text-[var(--color-ink)]">Active</span>
                        </label>
                    </div>

                    <div class="mt-4">
                        <p class="block text-sm font-medium text-[var(--color-ink)] mb-2">Attribute Combination</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($variantAttributes as $attribute)
                                @foreach($attribute->values as $value)
                                    <label class="inline-flex items-center gap-2 px-2 py-1 border hairline rounded-lg cursor-pointer hover:bg-[var(--color-paper-dim)] transition text-xs">
                                        <input type="checkbox" name="variants[__INDEX__][attribute_values][]" value="{{ $value->getKey() }}" class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                                        <span class="font-medium text-[var(--color-steel)]">{{ $attribute->name }}:</span>
                                        <span>{{ $value->value }}</span>
                                    </label>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
            <div class="flex items-center gap-2 mb-4">
                <i class="fa-solid fa-calendar-day text-[var(--color-red)] text-sm"></i>
                <h2 class="font-semibold text-[var(--color-ink)]">Publishing</h2>
            </div>
            <div class="space-y-4">
                <div>
                    <label for="published_at" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Published At</label>
                    <input type="datetime-local" id="published_at" name="published_at" value="{{ old('published_at', $product?->published_at?->format('Y-m-d\TH:i') ?? '') }}"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('published_at') border-[var(--color-red)] @enderror">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Leave empty to publish as soon as the product is active.</p>
                    @error('published_at') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
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
                    <input type="text" id="meta_title" name="meta_title" value="{{ old('meta_title', $product->meta_title ?? '') }}" maxlength="255"
                           class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('meta_title') border-[var(--color-red)] @enderror"
                           placeholder="{{ old('name', $product->name ?? 'Product name') }}">
                    <p class="mt-1 text-xs text-[var(--color-steel)]">Falls back to the product name.</p>
                    @error('meta_title') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="meta_description" class="block text-sm font-medium text-[var(--color-ink)] mb-1">Meta Description</label>
                    <textarea id="meta_description" name="meta_description" rows="3" maxlength="500"
                              class="w-full border hairline rounded-lg bg-[var(--color-paper)] px-4 py-2.5 focus:ring-2 focus:ring-[var(--color-red)] @error('meta_description') border-[var(--color-red)] @enderror">{{ old('meta_description', $product->meta_description ?? '') }}</textarea>
                    @error('meta_description') <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        @if($isEdit)
            <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
                <h2 class="font-semibold text-[var(--color-ink)] mb-1">Images</h2>
                <p class="text-xs text-[var(--color-steel)] mb-3">
                    {{ $existingImageCount }} image{{ $existingImageCount === 1 ? '' : 's' }} attached.
                </p>
                <a href="{{ route('admin.products.images', $product->getKey()) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--color-ink)] text-white text-sm font-medium hover:bg-[var(--color-ink-soft)] transition">
                    <i class="fa-solid fa-images"></i> Manage Images
                </a>
            </div>
        @endif
    </div>
</div>