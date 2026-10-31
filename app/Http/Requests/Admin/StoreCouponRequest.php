<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Store\Coupon\StoreCouponRequest as BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Overrides rules() in full, so it also inherits prepareForValidation() from the
 * base class — that is what upper-cases `code` before the unique rule runs, and
 * it is why a lowercase submission is rejected as a duplicate instead of
 * 500-ing on the coupons_code_unique index.
 */
class StoreCouponRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->whereNull('deleted_at')],
            'type' => 'required|string|in:percentage,fixed,free_shipping',
            'value' => ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                if ($this->input('type') === 'percentage' && $value > 100) {
                    $fail('Percentage discount cannot exceed 100%.');
                }
            }],
            'percentage' => 'nullable|numeric|min:0|max:100',
            'minimum_order' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Coupon code is required.',
            'code.unique' => 'This coupon code already exists.',
            'type.required' => 'Coupon type is required.',
            'type.in' => 'Coupon type must be one of: percentage, fixed, free_shipping.',
            'value.required' => 'Coupon value is required.',
            'value.numeric' => 'Coupon value must be a valid number.',
            'value.min' => 'Coupon value must be at least 0.',
            'percentage.numeric' => 'Percentage must be a valid number.',
            'percentage.min' => 'Percentage must be at least 0.',
            'percentage.max' => 'Percentage cannot exceed 100.',
            'minimum_order.numeric' => 'Minimum order amount must be a valid number.',
            'minimum_order.min' => 'Minimum order amount must be at least 0.',
            'maximum_discount.numeric' => 'Maximum discount amount must be a valid number.',
            'maximum_discount.min' => 'Maximum discount amount must be at least 0.',
            'usage_limit.integer' => 'Usage limit must be an integer.',
            'usage_limit.min' => 'Usage limit must be at least 1.',
            'per_customer_limit.integer' => 'Per customer limit must be an integer.',
            'per_customer_limit.min' => 'Per customer limit must be at least 1.',
            'starts_at.date' => 'Start date must be a valid date.',
            'expires_at.date' => 'Expiry date must be a valid date.',
            'expires_at.after_or_equal' => 'Expiry date must be on or after the start date.',
        ];
    }
}
