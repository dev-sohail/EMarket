<?php

namespace App\Http\Requests\Store\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
     * The user being updated, as bound by the route.
     */
    private function user(): Model
    {
        $user = $this->route('user');

        abort_unless($user instanceof Model, 404, 'Unable to resolve the user being updated.');

        return $user;
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
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($this->user())],
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'sometimes|required|array|min:1',
            'roles.*' => 'exists:roles,_id',
            'is_active' => 'boolean',
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
            'name.required' => 'User name is required.',
            'name.string' => 'User name must be a valid string.',
            'name.max' => 'User name must not exceed 255 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'phone.unique' => 'This phone number is already registered.',
            'password.string' => 'Password must be a valid string.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'roles.required' => 'At least one role must be assigned.',
            'roles.array' => 'Roles must be an array.',
            'roles.min' => 'At least one role must be assigned.',
            'roles.*.exists' => 'One or more selected roles do not exist.',
        ];
    }
}
