<?php

namespace App\Http\Requests\Store\Coupon;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateCouponRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Normalise the code before the unique rule compares it.
     *
     * CouponService::update() upper-cases the code after validation, so
     * validating the raw input checked a different value from the one that gets
     * stored: a lowercase submission found no match, passed, and then collided
     * with the existing upper-case row on the coupons_code_unique index. Doing
     * the normalisation here makes the rule and the persisted value the same
     * string. The service's own strtoupper() is left alone and is now a no-op.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => Str::upper(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * The coupon being updated, as bound by the route.
     *
     * An unbound route parameter is a hard 404 rather than a null: the only use
     * below is a comparison against this record's own key, and a null key turns
     * it into a check that silently matches nothing.
     *
     * Protected because the admin subclass re-declares rules() and has to reach
     * the same rule through uniqueIgnoringCurrent().
     */
    protected function coupon(): Model
    {
        $coupon = $this->route('coupon');

        abort_unless($coupon instanceof Model, 404, 'Unable to resolve the coupon being updated.');

        return $coupon;
    }

    /**
     * `unique` ignores a record by its primary key, and this schema has no
     * `id` column anywhere: the key is the prefixed `_id`. Passing the bare key
     * value to ignore() would set the id column back to `id`, so the rule would
     * compile `where id <> ...` against a column that does not exist; passing a
     * null key stops ignoring at all, so the record is compared against itself
     * and every save reports "already taken". Handing the rule the model makes
     * it read the real key name off it.
     *
     * `whereNull('deleted_at')` is the other half of the same change: the
     * `coupons_code_unique` index is composite over (code, deleted_at) since the
     * 2026_09_28_140000 migration, so a soft-deleted coupon no longer holds its
     * code. Without this the rule keeps seeing the trashed row through the
     * query builder and rejects a value the index now accepts. The upper-casing
     * in prepareForValidation() still happens first, so the comparison is made
     * against the persisted form of the code.
     *
     * Protected for the same reason as coupon(): the admin subclass
     * re-declares rules() and calls this.
     */
    protected function uniqueIgnoringCurrent(string $column): Unique
    {
        return Rule::unique('coupons', $column)
            ->ignore($this->coupon())
            ->whereNull('deleted_at');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', $this->uniqueIgnoringCurrent('code')],
            'type' => 'sometimes|required|string|in:percentage,fixed,free_shipping',
            'value' => 'sometimes|required|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'maximum_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'applies_to' => 'sometimes|required|string|in:all,specific_categories,specific_products',
            'applies_to_categories' => 'required_if:applies_to,specific_categories|array',
            'applies_to_categories.*' => 'exists:categories,_id',
            'applies_to_products' => 'required_if:applies_to,specific_products|array',
            'applies_to_products.*' => 'exists:products,_id',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'code.required' => 'Coupon code is required.',
            'code.unique' => 'This coupon code already exists.',
            'type.required' => 'Coupon type is required.',
            'type.in' => 'Coupon type must be one of: percentage, fixed, free_shipping.',
            'value.required' => 'Coupon value is required.',
            'value.numeric' => 'Coupon value must be a valid number.',
            'value.min' => 'Coupon value must be at least 0.',
            'minimum_order_amount.numeric' => 'Minimum order amount must be a valid number.',
            'minimum_order_amount.min' => 'Minimum order amount must be at least 0.',
            'maximum_discount_amount.numeric' => 'Maximum discount amount must be a valid number.',
            'maximum_discount_amount.min' => 'Maximum discount amount must be at least 0.',
            'usage_limit.integer' => 'Usage limit must be an integer.',
            'usage_limit.min' => 'Usage limit must be at least 1.',
            'usage_limit_per_user.integer' => 'Usage limit per user must be an integer.',
            'usage_limit_per_user.min' => 'Usage limit per user must be at least 1.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'applies_to.required' => 'Applies to field is required.',
            'applies_to.in' => 'Applies to must be one of: all, specific_categories, specific_products.',
            'applies_to_categories.array' => 'Categories must be an array.',
            'applies_to_categories.*.exists' => 'One or more selected categories do not exist.',
            'applies_to_products.array' => 'Products must be an array.',
            'applies_to_products.*.exists' => 'One or more selected products do not exist.',
        ];
    }
}
