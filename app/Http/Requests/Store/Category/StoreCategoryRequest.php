<?php

namespace App\Http\Requests\Store\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->whereNull('deleted_at')],
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:categories,_id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'boolean',
            'show_on_home' => 'boolean',
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
