<?php

namespace App\Http\Requests\Checkout;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isGuest = ! auth()->check();

        return [
            'name' => [Rule::requiredIf($isGuest), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf($isGuest), 'nullable', 'email', 'max:255'],
            'phone' => [Rule::requiredIf($isGuest), 'nullable', 'string', 'max:20'],

            'shipping_address_id' => ['nullable', 'exists:customer_addresses,_id'],
            'shipping_use_new_address' => ['nullable', 'in:1'],

            'shipping_first_name' => ['nullable', 'string', 'max:255'],
            'shipping_last_name' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['required_if:shipping_use_new_address,1', 'string', 'max:255'],
            'shipping_address2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required_if:shipping_use_new_address,1', 'string', 'max:255'],
            'shipping_state' => ['required_if:shipping_use_new_address,1', 'string', 'max:255'],
            'shipping_zip_code' => ['required_if:shipping_use_new_address,1', 'string', 'max:20'],
            'shipping_phone' => ['required_if:shipping_use_new_address,1', 'string', 'max:20'],

            'shipping_method_id' => ['required', 'string', 'exists:shipping_methods,_id,is_active,1'],
            'payment_method' => ['required', 'string', Rule::exists(PaymentMethod::class, 'code')->where('is_active', true)],

            'coupon_code' => ['nullable', 'string', 'max:50'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasSavedAddress = $this->filled('shipping_address_id');
            $hasNewAddress = $this->boolean('shipping_use_new_address');

            if (! $hasSavedAddress && ! $hasNewAddress) {
                $validator->errors()->add('shipping_address_id', 'Please select a saved address or enter a new shipping address.');
            }

            if ($hasSavedAddress && $hasNewAddress) {
                $validator->errors()->add('shipping_address_id', 'Please select either a saved address or enter a new one, not both.');
            }

            if ($hasSavedAddress) {
                $customer = auth()->user()?->customer;
                $ownsAddress = $customer
                    && $customer->addresses()
                        ->whereKey($this->input('shipping_address_id'))
                        ->exists();

                if (! $ownsAddress) {
                    $validator->errors()->add('shipping_address_id', 'Selected address does not exist. Please choose a valid saved address or enter a new one.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required_if' => 'Please enter your full name.',
            'email.required_if' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required_if' => 'Please enter your phone number.',

            'shipping_address_id.exists' => 'Selected address does not exist. Please choose a valid saved address or enter a new one.',

            'shipping_address.required' => 'Street address is required.',
            'shipping_city.required' => 'City is required.',
            'shipping_state.required' => 'Province / Canton is required.',
            'shipping_zip_code.required' => 'Postal code is required.',
            'shipping_phone.required' => 'Phone number is required for delivery.',

            'shipping_method_id.required' => 'Please select a shipping method.',
            'shipping_method_id.exists' => 'Selected shipping method is no longer available. Please choose another.',

            'payment_method.required' => 'Please select a payment method.',
            'payment_method.exists' => 'Selected payment method is no longer available. Please choose another.',

            'notes.max' => 'Order notes cannot exceed 1000 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'shipping_address' => 'street address',
            'shipping_city' => 'city',
            'shipping_state' => 'province',
            'shipping_zip_code' => 'postal code',
            'shipping_phone' => 'phone number',
            'shipping_method_id' => 'shipping method',
            'payment_method' => 'payment method',
        ];
    }
}
