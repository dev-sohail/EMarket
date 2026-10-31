<?php

namespace App\Http\Requests\Admin\ApiManagement;

use App\Models\ApiDefinition;
use Illuminate\Validation\Rule;

/**
 * Validates the manual "edit a definition" form.
 *
 * Two fields are not taken from the request, for the same reason as on store:
 *
 *  - `source` is decided by what generated the definition, not by a form field.
 *  - `slug` is ignored for a reflected definition, because it is the key the
 *    reflector matches on. Accepting a changed slug would orphan the definition
 *    and let the next regeneration quietly create a second one at the model's
 *    own slug, leaving two definitions claiming the same model.
 */
class UpdateApiDefinitionRequest extends StoreApiDefinitionRequest
{
    public function rules(): array
    {
        $definition = $this->route('apiDefinition');

        $rules = [
            'name' => 'required|string|max:255',
            'slug' => [
                'required', 'string', 'max:255', 'lowercase', 'alpha_dash:ascii',
                Rule::unique('api_definitions', 'slug')
                    ->ignore($definition?->getKey(), '_id'),
            ],
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];

        // A reflected definition renders the slug readonly and posts nothing for
        // it, so the field is simply not expected here. Whatever arrives is
        // discarded in definitionAttributes().
        if ($this->isReflected()) {
            unset($rules['slug']);
        }

        return $rules;
    }

    /**
     * The values actually written, with the system-owned fields pinned.
     *
     * @return array<string, mixed>
     */
    public function definitionAttributes(): array
    {
        $definition = $this->route('apiDefinition');

        $attributes = $this->validated();

        // Pinned rather than trusted: a crafted POST must not be able to
        // relabel a definition, which would hide the regenerate button and
        // invite a regeneration that overwrites hand-written endpoints.
        $attributes['source'] = $definition->getAttribute('source');

        // A reflected definition renders the slug readonly and posts nothing for
        // it. Pin it, so neither the browser nor a crafted POST can rename it
        // out from under the reflector. A manual definition keeps its own
        // editable slug.
        if ($this->isReflected()) {
            $attributes['slug'] = $definition->getAttribute('slug');
        }

        return $attributes;
    }

    protected function isReflected(): bool
    {
        $definition = $this->route('apiDefinition');

        return $definition instanceof ApiDefinition
            && $definition->source === 'reflection';
    }
}
