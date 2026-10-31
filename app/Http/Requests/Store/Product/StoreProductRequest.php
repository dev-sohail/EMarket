<?php

namespace App\Http\Requests\Store\Product;

use App\Models\AttributeValue;
use App\Rules\SafeHtml;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Checkbox toggles: an absent toggle is submitted as 0, which is the
     * correct default for a product that does not exist yet.
     *
     * @var array<int, string>
     */
    private const TOGGLE_KEYS = [
        'is_active',
        'is_featured',
        'is_new',
        'return_allowed',
    ];

    /**
     * The datetime-local format the admin product form submits.
     */
    private const SCHEDULE_FORMAT = '!Y-m-d\TH:i';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Normalise every field the rules below compare, plus the one field that
     * carries operator markup.
     *
     * The rich-text sanitising has to happen here rather than in the rule:
     * Validator::validated() reads the snapshot taken when the validator is
     * constructed, so a value rewritten from inside SafeHtml::validate() would
     * still be persisted raw. A `description` that is not a string is handed on
     * untouched so the `string` rule reports it as a 422 - prepareForValidation()
     * runs before the validator exists and must not throw.
     */
    public function prepareForValidation(): void
    {
        $merge = [];

        foreach (['brand_ids', 'category_ids', 'tags'] as $key) {
            $ids = $this->input($key);

            if (is_array($ids)) {
                $merge[$key] = $this->identifierList($ids);
            }
        }

        $attributes = $this->input('attributes');

        if (is_array($attributes)) {
            $merge['attributes'] = $this->buildAttributes($attributes);
        }

        $variants = $this->input('variants');

        if (is_array($variants)) {
            $merge['variants'] = $this->buildVariants($variants);
        }

        $description = $this->input('description');

        if (is_string($description)) {
            $merge['description'] = SafeHtml::sanitize($description, SafeHtml::PROFILE_CONTENT);
        }

        // Set default values for database fields that cannot be null
        $merge['discount_value'] = $this->filled('discount_value') ? $this->input('discount_value') : 0;
        // Only set optional numeric fields if they are filled to avoid overwriting existing values with 0
        if ($this->filled('sale_price')) {
            $merge['sale_price'] = $this->input('sale_price');
        }
        if ($this->filled('cost_price')) {
            $merge['cost_price'] = $this->input('cost_price');
        }
        if ($this->filled('weight')) {
            $merge['weight'] = $this->input('weight');
        }
        if ($this->filled('length')) {
            $merge['length'] = $this->input('length');
        }
        if ($this->filled('width')) {
            $merge['width'] = $this->input('width');
        }
        if ($this->filled('height')) {
            $merge['height'] = $this->input('height');
        }

        $merge['published_at'] = $this->normalizeDateTime($this->input('published_at'));

        foreach (self::TOGGLE_KEYS as $toggle) {
            $merge[$toggle] = $this->toBooleanInt($this->input($toggle));
        }

        $this->merge($merge);
    }

    /**
     * Strip the empty placeholders an HTML multi-select leaves behind and
     * de-duplicate, so the identifier rules below see real values only.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<int, string>
     */
    private function identifierList(array $values): array
    {
        $identifiers = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $identifier = trim((string) $value);

            if ($identifier !== '') {
                $identifiers[] = $identifier;
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * Rows that are not arrays, or that select no value, are not part of the
     * assignment set at all. Anything else is passed through untouched so the
     * validation rules - not this method - decide what is acceptable.
     *
     * @param  array<array-key, mixed>  $attributes
     * @return array<array-key, array<string, mixed>>
     */
    private function buildAttributes(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }

            $values = $attribute['values'] ?? [];

            if (is_array($values)) {
                $values = $this->identifierList($values);

                if ($values === []) {
                    continue;
                }
            }

            $attribute['values'] = $values;
            $normalized[] = $attribute;
        }

        return $normalized;
    }

    /**
     * Total by construction: a scalar instead of the array, a non-array row or
     * a non-array attribute_values member must all be reported as a 422 by the
     * validator, never as a TypeError thrown from here - prepareForValidation()
     * runs before the validator exists.
     *
     * @param  array<array-key, mixed>  $variants
     * @return array<array-key, array<string, mixed>>
     */
    private function buildVariants(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $key => $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $variant['is_active'] = $this->toBooleanInt($variant['is_active'] ?? null);
            $variant['price_add_to_main'] = $this->toBooleanInt($variant['price_add_to_main'] ?? null);

            if (array_key_exists('attribute_values', $variant)) {
                $variant['attribute_values'] = is_array($variant['attribute_values'])
                    ? $this->sortedIdentifiers($variant['attribute_values'])
                    : $variant['attribute_values'];
            }

            $normalized[$key] = $variant;
        }

        return $normalized;
    }

    /**
     * The combination key of a variant is derived from its attribute values,
     * so the same set in a different order has to collapse to the same key.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<int, string>
     */
    private function sortedIdentifiers(array $values): array
    {
        $identifiers = $this->identifierList($values);

        sort($identifiers);

        return $identifiers;
    }

    /**
     * Coerce a submitted flag to 0/1 without ever warning or throwing, which
     * filter_var() does when it is handed an array.
     */
    private function toBooleanInt(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value) || is_float($value)) {
            return $value ? 1 : 0;
        }

        if (! is_string($value)) {
            return 0;
        }

        return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    /**
     * An empty or absent schedule clears the publish date. Anything this
     * method cannot read is handed to the validator untouched, so the `date`
     * rule reports a 422 rather than prepareForValidation() throwing before
     * validation has even started.
     */
    private function normalizeDateTime(mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        $parsed = DateTimeImmutable::createFromFormat(self::SCHEDULE_FORMAT, trim($value));
        $errors = DateTimeImmutable::getLastErrors();

        $isValid = $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));

        return $isValid ? $parsed->format('Y-m-d H:i:s') : $value;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->whereNull('deleted_at')],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->whereNull('deleted_at')],
            'description' => ['required', 'string', 'max:16000', new SafeHtml(SafeHtml::PROFILE_CONTENT)],
            'short_description' => 'nullable|string|max:500',
            'base_price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0|lt:base_price',
            'discount_type' => 'nullable|in:none,percent,fixed',
            'discount_value' => ['nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                if ($this->input('discount_type') === 'percent' && $value > 100) {
                    $fail('Percentage discount cannot exceed 100%.');
                }
            }],
            'cost_price' => 'nullable|numeric|min:0',
            'stock_status' => 'sometimes|required|in:in_stock,out_of_stock,on_backorder',
            'stock_quantity' => 'nullable|integer|min:0',
            'category_ids' => 'sometimes|required|array',
            'category_ids.*' => 'exists:categories,_id',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'exists:brands,_id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,_id',
            'images' => $this->isMethod('POST') ? 'required|array|min:1' : 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'is_new' => 'sometimes|boolean',
            'return_allowed' => 'sometimes|boolean',
            'published_at' => 'nullable|date',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'attributes' => 'nullable|array|max:50',
            'attributes.*.attribute_id' => 'required_with:attributes|exists:attributes,_id',
            'attributes.*.values' => 'required_with:attributes|array|max:100',
            'attributes.*.values.*' => ['string', Rule::exists('attribute_values', '_id')],
            'variants' => 'nullable|array|max:100',
            'variants.*.name' => 'required_with:variants|string|max:255',
            'variants.*.sku' => 'required_with:variants|string|max:100|distinct|unique:product_variants,sku',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.stock' => 'required_with:variants|integer|min:0',
            'variants.*.price_add_to_main' => 'nullable|boolean',
            'variants.*.is_active' => 'nullable|boolean',
            'variants.*.attribute_values' => 'nullable|array|max:20',
            'variants.*.attribute_values.*' => ['string', Rule::exists('attribute_values', '_id')],
            'variants.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];
    }

    /**
     * A variant may only be described with values of the attributes the product
     * itself declares. The pivot carries no foreign key, so a value smuggled in
     * from another attribute would be stored as a row whose attribute_id nobody
     * declared, which the storefront filters can never resolve.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $declaredAttributeIds = $this->declaredAttributeIds();

            if ($declaredAttributeIds === []) {
                return;
            }

            $variants = $this->input('variants');

            if (! is_array($variants)) {
                return;
            }

            $owningAttributeIds = $this->owningAttributeIds($this->variantAttributeValueIds($variants));

            foreach ($variants as $index => $variant) {
                if (! is_array($variant)) {
                    continue;
                }

                foreach ((array) ($variant['attribute_values'] ?? []) as $valueId) {
                    if (! is_scalar($valueId)) {
                        continue;
                    }

                    $attributeId = $owningAttributeIds[(string) $valueId] ?? null;

                    if ($attributeId !== null && ! in_array($attributeId, $declaredAttributeIds, true)) {
                        $validator->errors()->add(
                            "variants.{$index}.attribute_values",
                            'Each selected variant attribute value must belong to an attribute declared on this product.'
                        );
                    }
                }
            }
        });
    }

    /**
     * @param  array<array-key, mixed>  $variants
     * @return array<int, string>
     */
    private function variantAttributeValueIds(array $variants): array
    {
        $valueIds = [];

        foreach ($variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            foreach ((array) ($variant['attribute_values'] ?? []) as $valueId) {
                if (is_scalar($valueId)) {
                    $valueIds[] = trim((string) $valueId);
                }
            }
        }

        return $valueIds;
    }

    /**
     * @return array<int, string>
     */
    private function declaredAttributeIds(): array
    {
        $attributes = $this->input('attributes');

        if (! is_array($attributes)) {
            return [];
        }

        $identifiers = [];

        foreach ($attributes as $attribute) {
            if (is_array($attribute) && isset($attribute['attribute_id']) && is_scalar($attribute['attribute_id'])) {
                $identifiers[] = trim((string) $attribute['attribute_id']);
            }
        }

        return array_values(array_filter(array_unique($identifiers), fn (string $id) => $id !== ''));
    }

    /**
     * @param  array<int, string>  $valueIds
     * @return array<string, string>
     */
    private function owningAttributeIds(array $valueIds): array
    {
        $valueIds = array_values(array_filter(array_unique($valueIds), fn (string $id) => $id !== ''));

        if ($valueIds === []) {
            return [];
        }

        return AttributeValue::whereIn('_id', $valueIds)
            ->pluck('attribute_id', '_id')
            ->map(fn ($attributeId) => (string) $attributeId)
            ->all();
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'name.required' => 'Product name is required.',
            'name.string' => 'Product name must be a valid string.',
            'name.max' => 'Product name must not exceed 255 characters.',
            'slug.required' => 'Product slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'This SKU is already taken.',
            'description.required' => 'Product description is required.',
            'description.max' => 'Product description must not exceed 16000 characters.',
            'base_price.required' => 'Base price is required.',
            'base_price.numeric' => 'Base price must be a valid number.',
            'base_price.min' => 'Base price must be at least 0.',
            'compare_price.gte' => 'Compare price must be greater than or equal to base price.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'brand_id.exists' => 'Selected brand does not exist.',
            'tags.*.exists' => 'One or more selected tags do not exist.',
            'images.required' => 'At least one product image is required.',
            'images.array' => 'Images must be an array.',
            'images.min' => 'At least one product image is required.',
            'images.*.image' => 'Each image must be a valid image file.',
            'images.*.mimes' => 'Images must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'images.*.max' => 'Each image must not exceed 2MB.',
            'attributes.*.values.*.exists' => 'One or more selected attribute values do not exist.',
            'variants.*.name.required_with' => 'Variant name is required when variants are provided.',
            'variants.*.sku.required_with' => 'Variant SKU is required when variants are provided.',
            'variants.*.sku.unique' => 'This variant SKU is already taken.',
            'variants.*.price.required_with' => 'Variant price is required when variants are provided.',
            'variants.*.stock.required_with' => 'Variant stock is required when variants are provided.',
            'variants.*.attribute_values.*.exists' => 'One or more selected attribute values do not exist.',
            'variants.*.attribute_values' => 'Each selected variant attribute value must belong to an attribute declared on this product.',
        ];
    }
}
