<?php

namespace App\Http\Requests\Ajax;

use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'add' => [
                'product_id' => ['required', 'exists:products,_id'],
                'variant_id' => ['nullable', 'exists:product_variants,_id', 'belongs_to_product:product_id'],
                'quantity' => ['required', 'integer', 'min:1'],
            ],
            'update' => [
                'cart_item_id' => ['required', 'exists:cart_items,_id'],
                'quantity' => ['required', 'integer', 'min:0'],
            ],
            'remove' => [
                'cart_item_id' => ['required', 'exists:cart_items,_id'],
            ],
            default => [],
        };
    }
}
