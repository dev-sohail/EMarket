<?php

namespace App\Http\Requests\Store\Attribute;

use App\Enums\AttributeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
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
     * one row was left blank submits `[null]`, which satisfies `min:1` on its
     * count while carrying no value at all — the service then reads it as
     * "remove every value" and deletes the whole list. Filtering here keeps an
     * empty list empty, so `min:1` reports the missing value instead of
     * destroying the existing ones, and `distinct` compares real strings.
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
            'values' => $values,
            'is_filterable' => $this->boolean('is_filterable'),
            'is_variant' => $this->boolean('is_variant'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:attributes,slug',
            'type' => ['required', 'string', Rule::in(AttributeType::validationValues())],
            'values' => $this->valueListRule(),
            'values.*' => ['string', 'max:255', 'distinct'],
            'is_filterable' => 'boolean',
            'is_variant' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    /**
     * A type that picks from a predefined list needs at least one value; a
     * free-typed one (`text`, `number`) does not, and demanding a value there
     * made those two types impossible to create at all.
     *
     * @return array<int, mixed>
     */
    private function valueListRule(): array
    {
        $rules = ['array'];

        return $this->resolvedType()?->requiresPresetValues()
            ? array_merge($rules, ['required', 'min:1'])
            : array_merge($rules, ['nullable']);
    }

    /**
     * The requested type as an enum case, or null when it is absent or not one
     * the application knows, in which case `type` itself reports the problem.
     */
    private function resolvedType(): ?AttributeType
    {
        $type = $this->input('type');

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
            'values.required' => 'At least one attribute value is required.',
            'values.array' => 'Values must be an array.',
            'values.min' => 'At least one attribute value is required.',
            'values.*.string' => 'Each value must be a valid string.',
            'values.*.max' => 'Each value must not exceed 255 characters.',
            'values.*.distinct' => 'Each value must be unique.',
            'sort_order.integer' => 'Sort order must be an integer.',
            'sort_order.min' => 'Sort order must be at least 0.',
        ];
    }
}
