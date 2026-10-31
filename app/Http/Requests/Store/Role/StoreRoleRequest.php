<?php

namespace App\Http\Requests\Store\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'required|array|min:1',
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
            'permissions.required' => 'At least one permission must be selected.',
            'permissions.array' => 'Permissions must be an array.',
            'permissions.min' => 'At least one permission must be selected.',
            'permissions.*.string' => 'Each permission must be a valid string.',
            'permissions.*.max' => 'Each permission must not exceed 255 characters.',
        ];
    }
}
