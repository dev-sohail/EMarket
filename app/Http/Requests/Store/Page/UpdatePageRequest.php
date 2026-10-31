<?php

namespace App\Http\Requests\Store\Page;

use App\Rules\SafeHtml;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdatePageRequest extends FormRequest
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
     * The page being updated, as bound by the route.
     *
     * An unbound route parameter is a hard 404 rather than a null: the only use
     * below is a comparison against this record's own key, and a null key turns
     * it into a check that silently matches nothing.
     */
    private function page(): Model
    {
        $page = $this->route('page');

        abort_unless($page instanceof Model, 404, 'Unable to resolve the page being updated.');

        return $page;
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
     * `pages_slug_unique` index is composite over (slug, deleted_at) since the
     * 2026_09_28_140000 migration, so a soft-deleted page no longer holds its
     * slug. Without this the rule keeps seeing the trashed row through the
     * query builder and rejects a value the index now accepts.
     */
    private function uniqueIgnoringCurrent(string $column): Unique
    {
        return Rule::unique('pages', $column)
            ->ignore($this->page())
            ->whereNull('deleted_at');
    }

    /**
     * Normalise the rich-text field before the validator is built.
     *
     * This has to happen here rather than in the rule: Validator::validated()
     * reads the snapshot taken when the validator is constructed, so a value
     * rewritten from inside validate() would still be persisted raw.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge([
                'content' => SafeHtml::sanitize($this->input('content'), SafeHtml::PROFILE_CONTENT),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'required', 'string', 'max:255', $this->uniqueIgnoringCurrent('slug')],
            'content' => ['sometimes', 'required', 'string', 'max:16000', new SafeHtml(SafeHtml::PROFILE_CONTENT)],
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:2048', 'dimensions:min_width=1,min_height=1,max_width=4000,max_height=4000'],
            'status' => 'sometimes|in:draft,published',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
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
            'title.required' => 'Page title is required.',
            'title.string' => 'Page title must be a valid string.',
            'title.max' => 'Page title must not exceed 255 characters.',
            'slug.required' => 'Page slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'content.required' => 'Page content is required.',
            'content.max' => 'Page content must not exceed 16000 characters.',
            'featured_image.image' => 'Featured image must be a valid image file.',
            'featured_image.mimes' => 'Featured image must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'featured_image.mimetypes' => 'Featured image must be a JPEG, PNG, GIF or WebP file.',
            'featured_image.max' => 'Featured image must not exceed 2MB.',
            'featured_image.dimensions' => 'Featured image must be between 1x1 and 4000x4000 pixels.',
            'sort_order.integer' => 'Sort order must be an integer.',
            'sort_order.min' => 'Sort order must be at least 0.',
            'meta_title.max' => 'Meta title must not exceed 255 characters.',
            'meta_description.max' => 'Meta description must not exceed 500 characters.',
            'meta_keywords.max' => 'Meta keywords must not exceed 255 characters.',
        ];
    }
}
