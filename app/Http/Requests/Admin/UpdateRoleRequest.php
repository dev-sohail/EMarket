<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // The role key is the string `_id`, not an auto-increment `id`. Passing
        // the bare key to ignore() makes Unique default its idColumn to 'id',
        // which does not exist on the roles table, so the "ignore this row"
        // clause never matched and the role always collided with itself: every
        // save without a rename failed with "The name has already been taken."
        // Handing ignore() the model lets it read the real primary key.
        $role = $this->route('role');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($role)],
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,_id',
        ];
    }
}
