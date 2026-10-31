<?php

namespace App\Http\Requests\Store\Brand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', Rule::unique('brands', 'slug')->whereNull('deleted_at')],
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
