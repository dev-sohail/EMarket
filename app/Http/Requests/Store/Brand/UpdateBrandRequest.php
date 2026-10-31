<?php

namespace App\Http\Requests\Store\Brand;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateBrandRequest extends FormRequest
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
     * The brand being updated, as bound by the route.
     *
     * An unbound route parameter is a hard 404 rather than a null: the only use
     * below is a comparison against this record's own key, and a null key turns
     * it into a check that silently matches nothing.
     */
    private function brand(): Model
    {
        $brand = $this->route('brand');

        abort_unless($brand instanceof Model, 404, 'Unable to resolve the brand being updated.');

        return $brand;
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
     * `brands_slug_unique` index is composite over (slug, deleted_at) since the
     * 2026_09_28_140000 migration, so a soft-deleted brand no longer holds its
     * slug. Without this the rule keeps seeing the trashed row through the
     * query builder and rejects a value the index now accepts.
     */
    private function uniqueIgnoringCurrent(string $column): Unique
    {
        return Rule::unique('brands', $column)
            ->ignore($this->brand())
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
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', $this->uniqueIgnoringCurrent('slug')],
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
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
            'name.required' => 'Brand name is required.',
            'name.string' => 'Brand name must be a valid string.',
            'name.max' => 'Brand name must not exceed 255 characters.',
            'slug.required' => 'Brand slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'logo.image' => 'Logo must be a valid image file.',
            'logo.mimes' => 'Logo must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'logo.max' => 'Logo must not exceed 2MB.',
            'website.url' => 'Website must be a valid URL.',
            'sort_order.integer' => 'Sort order must be an integer.',
            'sort_order.min' => 'Sort order must be at least 0.',
        ];
    }
}
