<?php

namespace App\Rules;

use App\Models\ProductVariant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BelongsToProduct implements ValidationRule
{
    public function __construct(protected string $productIdField) {}

    /**
     * Ensure the submitted variant actually belongs to the submitted product.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $productId = request()->input($this->productIdField);

        if (! $productId || ! $value) {
            return;
        }

        $belongs = ProductVariant::where('_id', $value)
            ->where('product_id', $productId)
            ->exists();

        if (! $belongs) {
            $fail('The selected variant does not belong to the specified product.');
        }
    }
}
