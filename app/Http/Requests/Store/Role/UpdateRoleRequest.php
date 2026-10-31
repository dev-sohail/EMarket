<?php

namespace App\Http\Requests\Store\Role;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
     * The role being updated, as bound by the route.
     */
    private function role(): Model
    {
        $role = $this->route('role');

        abort_unless($role instanceof Model, 404, 'Unable to resolve the role being updated.');

        return $role;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($this->role())],
            'description' => 'nullable|string|max:500',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|max:255',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'name.required' => 'Role name is required.',
            'name.string' => 'Role name must be a valid string.',
            'name.max' => 'Role name must not exceed 255 characters.',
            'name.unique' => 'This role name already exists.',
            'description.string' => 'Description must be a valid string.',
            'description.max' => 'Description must not exceed 500 characters.',
            'permissions.array' => 'Permissions must be an array.',
            'permissions.*.string' => 'Each permission must be a valid string.',
            'permissions.*.max' => 'Each permission must not exceed 255 characters.',
        ];
    }
}
