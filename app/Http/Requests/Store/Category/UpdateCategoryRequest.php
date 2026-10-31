<?php

namespace App\Http\Requests\Store\Category;

use App\Models\Category as CategoryModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateCategoryRequest extends FormRequest
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
     * The category being updated, as bound by the route.
     *
     * An unbound route parameter is a hard 404 rather than a null: every use
     * below is a comparison against this record's own key, and a null key turns
     * each of them into a check that silently matches nothing.
     */
    private function category(): Model
    {
        $category = $this->route('category');

        abort_unless($category instanceof Model, 404, 'Unable to resolve the category being updated.');

        return $category;
    }

    /**
     * `unique` ignores a record by its primary key, and this schema has no
     * `id` column anywhere: the key is the prefixed `_id`. Ignoring with the
     * default id column compiles a where on a column that does not exist, and
     * ignoring with a null key silently stops ignoring at all, so the record is
     * compared against itself and every save reports "already taken". Handing
     * the rule the model makes it read the real key name off it.
     *
     * `whereNull('deleted_at')` is the other half of the same change: the
     * `categories_slug_unique` index is composite over (slug, deleted_at) since
     * the 2026_09_28_140000 migration, so a soft-deleted category no longer
     * holds its slug. Without this the rule keeps seeing the trashed row
     * through the query builder and rejects a value the index now accepts.
     */
    private function uniqueIgnoringCurrent(string $column): Unique
    {
        return Rule::unique('categories', $column)
            ->ignore($this->category())
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
            'parent_id' => 'nullable|exists:categories,_id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'sometimes|boolean',
            'show_on_home' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $category = $this->category();
            $parentId = $this->input('parent_id');

            if (! $parentId) {
                return;
            }

            if ($parentId === $category->getKey()) {
                $validator->errors()->add('parent_id', 'A category cannot be its own parent.');

                return;
            }

            // Walk up the chain checking we never land back on ourselves.
            $seen = [];
            $current = CategoryModel::find($parentId);

            while ($current) {
                if (in_array($current->getKey(), $seen, true) || $current->getKey() === $category->getKey()) {
                    $validator->errors()->add('parent_id', 'This would create a circular category reference.');

                    return;
                }

                $seen[] = $current->getKey();
                $current = $current->parent_id ? CategoryModel::find($current->parent_id) : null;
            }
        });
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'name.required' => 'Category name is required.',
            'name.string' => 'Category name must be a valid string.',
            'name.max' => 'Category name must not exceed 255 characters.',
            'slug.required' => 'Category slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'parent_id.exists' => 'Selected parent category does not exist.',
            'image.image' => 'Image must be a valid image file.',
            'image.mimes' => 'Image must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'image.max' => 'Image must not exceed 2MB.',
            'sort_order.integer' => 'Sort order must be an integer.',
            'sort_order.min' => 'Sort order must be at least 0.',
        ];
    }
}
