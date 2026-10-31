<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:percentage,fixed',
            'value' => 'sometimes|required|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,_id',
            'product_prices' => 'nullable|array',
            'product_prices.*' => 'nullable|numeric|min:0',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,_id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Campaign name is required.',
            'name.string' => 'Campaign name must be a valid string.',
            'name.max' => 'Flash sale name must not exceed 255 characters.',
            'type.required' => 'Campaign type is required.',
            'type.in' => 'Campaign type must be percentage or fixed.',
            'value.required' => 'Campaign value is required.',
            'value.numeric' => 'Campaign value must be a valid number.',
            'value.min' => 'Campaign value must be at least 0.',
            'starts_at.date' => 'Start date must be a valid date.',
            'ends_at.date' => 'End date must be a valid date.',
            'ends_at.after' => 'End date must be after the start date.',
            'product_ids.array' => 'Products must be an array.',
            'product_ids.*.exists' => 'One or more selected products do not exist.',
            'category_ids.array' => 'Categories must be an array.',
            'category_ids.*.exists' => 'One or more selected categories do not exist.',
        ];
    }
}
