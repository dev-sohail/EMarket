<?php

namespace App\Http\Requests\Store\Attribute;

use App\Enums\AttributeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateAttributeRequest extends FormRequest
{
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
     * Drop blank value rows and un-tick absent checkboxes before validating.
     *
     * An empty `<input name="values[]">` arrives as null, because Laravel's
     * ConvertEmptyStringsToNull middleware runs first. Left alone, a form whose
     * rows were all left blank submits `[null, null]`, which satisfies `min:1`
     * on its count while carrying no value at all — the service then reads it
     * as "remove every value" and deletes the whole list together with the
     * products pointing at it. Filtering here keeps an empty list empty, so
     * `min:1` reports the missing value instead of destroying the existing ones.
     *
     * An unticked checkbox sends nothing at all, so the flags are read with
     * `boolean()` and merged in either way: without this, clearing a flag was
     * impossible, because the absent key fell through the `sometimes` rule and
     * the previous value was kept.
     */
    protected function prepareForValidation(): void
    {
        $values = $this->input('values');

        if (is_array($values)) {
            $values = collect($values)
                ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : null)
                ->filter(fn (?string $value) => $value !== null && $value !== '')
                ->unique()
                ->values()
                ->all();
        }

        $this->merge([
            'is_filterable' => $this->boolean('is_filterable'),
            'is_variant' => $this->boolean('is_variant'),
        ]);

        // Only rewritten when the field was actually submitted: merging `values`
        // in as null would turn an untouched list into a validation error.
        if ($values !== null) {
            $this->merge(['values' => $values]);
        }
    }

    /**
     * The attribute being updated, as bound by the route.
     *
     * An unbound route parameter is a hard 404 rather than a null: the unique
     * ignore below compares against this record's own key, and a null key
     * silently stops ignoring at all, so the record is compared against itself
     * and every save reports "already taken".
     */
    private function attribute(): Model
    {
        $attribute = $this->route('attribute');

        abort_unless($attribute instanceof Model, 404, 'Unable to resolve the attribute being updated.');

        return $attribute;
    }

    /**
     * `unique` ignores a record by its primary key, and this schema has no
     * `id` column anywhere: the key is the prefixed `_id`. Ignoring with the
     * default id column compiles a where on a column that does not exist, so
     * handing the rule the model lets it read the real key name off it.
     */
    private function uniqueIgnoringCurrent(string $column): Unique
    {
        return Rule::unique('attributes', $column)->ignore($this->attribute());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', $this->uniqueIgnoringCurrent('slug')],
            'type' => ['sometimes', 'required', 'string', Rule::in(AttributeType::validationValues())],
            'values' => $this->valueListRule(),
            'values.*' => ['string', 'max:255', 'distinct'],
            'is_filterable' => 'sometimes|boolean',
            'is_variant' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    /**
     * `values` is only validated when the field is actually present, so a
     * request that never touched the value list leaves it alone. When it is
     * present, the type it belongs to decides whether an empty list is allowed:
     * a type that picks from a predefined list still needs at least one value,
     * while a free-typed one may legitimately be empty.
     *
     * @return array<int, mixed>
     */
    private function valueListRule(): array
    {
        $rules = ['sometimes', 'array'];

        return $this->resolvedType()?->requiresPresetValues()
            ? array_merge($rules, ['min:1'])
            : array_merge($rules, ['nullable']);
    }

    /**
     * The effective type: the submitted one, or the attribute's current type
     * when the request leaves it unchanged.
     */
    private function resolvedType(): ?AttributeType
    {
        $type = $this->has('type') ? $this->input('type') : $this->attribute()->getAttribute('type');

        return is_string($type) ? AttributeType::tryFrom($type) : null;
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'name.required' => 'Attribute name is required.',
            'name.string' => 'Attribute name must be a valid string.',
            'name.max' => 'Attribute name must not exceed 255 characters.',
            'slug.required' => 'Attribute slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'type.required' => 'Attribute type is required.',
            'type.in' => 'Attribute type must be one of: text, number, select, multiselect, color.',
            'values.array' => 'Values must be an array.',
            'values.min' => 'At least one value is required.',
            'values.*.string' => 'Each value must be a valid string.',
            'values.*.max' => 'Each value must not exceed 255 characters.',
            'values.*.distinct' => 'Each value must be unique.',
            'sort_order.integer' => 'Sort order must be an integer.',
            'sort_order.min' => 'Sort order must be at least 0.',
        ];
    }
}
