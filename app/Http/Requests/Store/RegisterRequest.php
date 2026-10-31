<?php

namespace App\Http\Requests\Store;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The email probe has to see soft-deleted rows.
     *
     * `users.email` keeps a PLAIN unique index - the 2026_09_28_140000 migration
     * deliberately left it alone - so a trashed user still occupies the address.
     * A soft-delete-blind `User::where(...)` found nothing, the form passed, and
     * the failure only surfaced as a redirect-with-error from the controller
     * instead of a 422 on the field. `withTrashed()` makes the two agree.
     *
     * This rule deliberately fails only in the one case the controller cannot
     * handle on its own: a LIVE user with no `customers` row to reconcile, where
     * registration would go on to `User::create()` a second row and die on the
     * index as a caught exception. Every other duplicate is already a policy
     * decision in RegisterController, and failing here would pre-empt it:
     *
     *  - a trashed user is refused there as a deactivated account, and  - a
     *    customer row, live, trashed, guest or already linked, is restored,
     *    relinked or refused according to which of those it is.
     *
     * Failing the rule replaces that per-table message with one generic
     * "already exists" string, and reports it as a 422 field error rather than
     * the flash the controller sets, so the form loses the instruction to sign
     * in or contact support.
     *
     * The message is unchanged and stays as non-enumerable as it already was: it
     * says only what the `unique:users,email` rules in the admin forms already
     * say for a live row.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    $existingUser = User::withTrashed()->where('email', $value)->first();

                    if (! $existingUser || $existingUser->trashed()) {
                        return;
                    }

                    if (Customer::withTrashed()->where('email', $value)->exists()) {
                        return;
                    }

                    $fail('An account with this email already exists. Please sign in instead.');
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
