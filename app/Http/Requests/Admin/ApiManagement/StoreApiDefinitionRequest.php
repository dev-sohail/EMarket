<?php

namespace App\Http\Requests\Admin\ApiManagement;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the manual "create a definition" form.
 *
 * `source` is deliberately absent from the rules, so it is never taken from the
 * request. It used to be an `in:manual,reflection` dropdown, which looked like
 * a setting but only ever wrote the string: choosing "reflection" produced an
 * active definition with no endpoints, no parameters and no handler, serving
 * nothing while appearing to work. Source is now decided by what actually
 * happened — the store action writes `manual`, and the reflector's write path
 * writes `reflection` — so it cannot be asserted by a form post.
 */
class StoreApiDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', 'lowercase', 'alpha_dash:ascii', 'unique:api_definitions,slug'],
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * The values actually written, with `source` set by the system.
     *
     * @return array<string, mixed>
     */
    public function definitionAttributes(): array
    {
        // array_merge rather than safe()->merge(): ValidatedInput::merge() is
        // fluent and returns itself, not an array.
        return array_merge($this->validated(), ['source' => 'manual']);
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'This slug is already in use by another API definition.',
        ];
    }
}
