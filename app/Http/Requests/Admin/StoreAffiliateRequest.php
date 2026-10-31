<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAffiliateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'string', 'exists:customers,_id', Rule::unique('affiliates', 'customer_id')->whereNull('deleted_at')],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'customer_id.unique' => 'This customer is already enrolled as an affiliate.',
            'commission_rate.numeric' => 'Commission rate must be a valid number.',
            'commission_rate.min' => 'Commission rate must be at least 0.',
            'commission_rate.max' => 'Commission rate may not exceed 100.',
        ];
    }
}
