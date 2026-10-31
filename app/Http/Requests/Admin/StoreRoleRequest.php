<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:roles,name',
            'slug' => 'nullable|string|max:255|unique:roles,slug',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,_id',
        ];
    }

    /**
     * The form has no slug input, so the `slug` unique rule above never fires:
     * it only guards a field the user never submits. The controller then
     * derives the slug from the name, and two names that slugify identically
     * ("Content Manager" and "Content-Manager") both passed the unique name
     * rule and then collided on the slug column, aborting the create with an
     * unhandled UniqueConstraintViolationException (HTTP 500).
     *
     * Check the derived value here so the user gets a form error instead. The
     * message is attached to `name` because that is the only field on the form
     * the user can actually change.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $slug = $this->input('slug') ?: Str::slug((string) $this->input('name'));

            if ($slug !== '' && Role::where('slug', $slug)->exists()) {
                $validator->errors()->add(
                    'name',
                    'Another role already uses this name, because both produce the same slug.'
                );
            }
        });
    }
}
