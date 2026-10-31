<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAffiliateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,active,suspended',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Affiliate status is required.',
            'status.in' => 'Affiliate status must be one of: pending, active, suspended.',
            'commission_rate.numeric' => 'Commission rate must be a valid number.',
            'commission_rate.min' => 'Commission rate must be at least 0.',
            'commission_rate.max' => 'Commission rate may not exceed 100.',
        ];
    }
}
