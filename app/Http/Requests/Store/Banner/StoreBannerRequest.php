<?php

namespace App\Http\Requests\Store\Banner;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $position = $this->input('position');

        $rules = [
            'position' => 'required|string|in:homepage,promotional,footer,sale',
            'url' => 'nullable|url|max:255',
            'url_2' => 'nullable|url|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
            'subtitle' => 'nullable|string|max:255',
        ];

        if ($position === 'sale') {
            $rules['color'] = ['required', 'string', 'max:7', function ($attribute, $value, $fail) {
                if (! preg_match('/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$/', $value)) {
                    $fail('Color must be a valid hex code (e.g. #D6001C).');
                }
            }];
            $rules['title'] = 'required|string|max:255';
            $rules['description'] = 'nullable|string';
            $rules['cta_text'] = 'nullable|string|max:255';
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096';
        } elseif ($position === 'footer') {
            $rules['title'] = 'nullable|string|max:255';
            $rules['description'] = 'nullable|string';
            $rules['subtitle'] = 'nullable|string|max:255';
            $rules['color'] = ['nullable', 'string', 'max:7', function ($attribute, $value, $fail) {
                if ($value !== '' && $value !== null && ! preg_match('/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$/', $value)) {
                    $fail('Color must be a valid hex code (e.g. #D6001C).');
                }
            }];
            $rules['cta_text'] = 'nullable|string|max:255';
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096';
        } else {
            $rules['image'] = 'required|image|mimes:jpeg,png,jpg,gif,webp|max:4096';
            $rules['color'] = ['nullable', 'string', 'max:7', function ($attribute, $value, $fail) {
                if ($value !== '' && $value !== null && ! preg_match('/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})$/', $value)) {
                    $fail('Color must be a valid hex code (e.g. #D6001C).');
                }
            }];
        }

        if (in_array($position, ['homepage', 'promotional'])) {
            $rules['title'] = 'required|string|max:255';
            $rules['description'] = 'nullable|string';
            $rules['cta_text'] = 'nullable|string|max:255';
            $rules['cta_text_2'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Banner title is required.',
            'image.required' => 'Banner image is required.',
            'image.image' => 'Image must be a valid image file.',
            'image.mimes' => 'Image must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'image.max' => 'Image must not exceed 4MB.',
            'color.required' => 'Background color is required for sale banners.',
            'color.max' => 'Color must not exceed 7 characters (e.g. #D6001C).',
            'color.regex' => 'Color must be a valid hex code (e.g. #D6001C).',
            'url.url' => 'URL must be a valid URL.',
            'position.required' => 'Banner position is required.',
            'position.in' => 'Position must be one of: homepage, promotional, footer, sale.',
            'starts_at.date' => 'Start date must be a valid date.',
            'ends_at.date' => 'End date must be a valid date.',
            'ends_at.after_or_equal' => 'End date must be on or after the start date.',
            'sort_order.integer' => 'Sort order must be an integer.',
            'sort_order.min' => 'Sort order must be at least 0.',
        ];
    }
}
