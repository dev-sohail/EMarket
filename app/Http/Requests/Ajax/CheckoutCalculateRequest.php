<?php

namespace App\Http\Requests\Ajax;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutCalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_method_id' => ['nullable', 'exists:shipping_methods,_id,is_active,1'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
