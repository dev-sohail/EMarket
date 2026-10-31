<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFlashSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $flashSaleId = $this->route('flashSale')?->getKey();

        return [
            'name' => 'sometimes|required|string|max:255',
            'sale_price' => 'sometimes|required|numeric|min:0',
            'starts_at' => 'sometimes|required|date',
            'ends_at' => 'sometimes|required|date|after:starts_at',
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
            'name.required' => 'Flash sale name is required.',
            'name.string' => 'Flash sale name must be a valid string.',
            'name.max' => 'Flash sale name must not exceed 255 characters.',
            'sale_price.required' => 'Sale price is required.',
            'sale_price.numeric' => 'Sale price must be a valid number.',
            'sale_price.min' => 'Sale price must be at least 0.',
            'starts_at.required' => 'Start date is required.',
            'starts_at.date' => 'Start date must be a valid date.',
            'ends_at.required' => 'End date is required.',
            'ends_at.date' => 'End date must be a valid date.',
            'ends_at.after' => 'End date must be after the start date.',
            'product_ids.array' => 'Products must be an array.',
            'product_ids.*.exists' => 'One or more selected products do not exist.',
            'category_ids.array' => 'Categories must be an array.',
            'category_ids.*.exists' => 'One or more selected categories do not exist.',
        ];
    }
}
