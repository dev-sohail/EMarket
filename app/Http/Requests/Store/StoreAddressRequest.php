<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreAddressRequest extends FormRequest
{
    /**
     * The text columns of `customer_addresses`, all `char()` in MySQL, so a
     * trailing space survives into the courier label and a "GB " country is not
     * the same string as "GB".
     *
     * @var list<string>
     */
    protected const TEXT_FIELDS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'type',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalised = [];

        foreach (static::TEXT_FIELDS as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            $normalised[$field] = is_string($value) ? trim($value) : $value;
        }

        // Country is always the store's default country — not editable by the user
        $normalised['country'] = Str::upper(trim((string) setting('default_country', 'CH')));

        $this->merge($normalised);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-.\s]{6,20}$/'],
            'company' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            // The column is NOT NULL, so a blank postcode reached MySQL as null
            // and failed the INSERT (STRICT_TRANS_TABLES) instead of the form —
            // the customer saw "a system error" for a field the form never asked
            // for. Required here, which is also what a delivery label needs.
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'type' => ['required', 'string', 'in:home,work,other'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'address_line_1' => 'street address',
            'address_line_2' => 'address line 2',
            'postal_code' => 'postal code',
            'country' => 'country',
            'type' => 'address type',
            'is_default' => 'default address',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Please enter the recipient\'s first name.',
            'last_name.required' => 'Please enter the recipient\'s last name.',
            'email.required' => 'Please enter an email address for delivery updates.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required' => 'Please enter a phone number for the courier.',
            'phone.regex' => 'Please use digits, spaces and + ( ) - . only, between 6 and 20 characters.',
            'address_line_1.required' => 'Please enter the street address.',
            'city.required' => 'Please enter the city.',
            'state.required' => 'Please enter the province, canton or state.',
            'postal_code.required' => 'Please enter the postal code.',
            'postal_code.max' => 'The postal code must not exceed 20 characters.',
        ];
    }
}
