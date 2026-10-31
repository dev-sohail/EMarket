<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The user being updated, as bound by the route.
     *
     * Named targetUser(), not user(): Illuminate\Http\Request already declares a
     * public `user()` returning the *authenticated* user, so declaring a private
     * `user()` here is a fatal error at class-load time
     * ("Access level to ...::user() must be public"). That killed the whole test
     * process with "Premature end of PHP process" rather than failing one test,
     * because the file cannot even be autoloaded. Note also that `$this->user()`
     * would have returned the acting admin, so the unique-ignore would have
     * excluded the wrong row.
     */
    private function targetUser(): Model
    {
        $user = $this->route('user');

        abort_unless($user instanceof Model, 404, 'Unable to resolve the user being updated.');

        return $user;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->targetUser())],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($this->targetUser())],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ];
    }
}
