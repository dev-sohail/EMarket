<?php

namespace App\Http\Requests\Ajax;

use Illuminate\Foundation\Http\FormRequest;

class AjaxReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,_id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => [
                'image',
                'mimes:jpeg,png,jpg,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
                'dimensions:min_width=1,min_height=1,max_width=4000,max_height=4000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => 'You may attach at most 5 images.',
            'images.*.image' => 'Each review attachment must be an image file.',
            'images.*.mimes' => 'Attachments must be in JPEG, PNG, JPG or WebP format.',
            'images.*.mimetypes' => 'Attachments must be JPEG, PNG or WebP files.',
            'images.*.max' => 'Each attachment must not exceed 2MB.',
            'images.*.dimensions' => 'Each attachment must be between 1x1 and 4000x4000 pixels.',
        ];
    }
}
