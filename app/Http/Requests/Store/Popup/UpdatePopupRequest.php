<?php

namespace App\Http\Requests\Store\Popup;

use App\Rules\SafeHtml;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        if ($this->has('custom_html')) {
            $this->merge([
                'custom_html' => SafeHtml::sanitize($this->input('custom_html'), SafeHtml::PROFILE_POPUP),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'max:4096', 'dimensions:min_width=1,min_height=1,max_width=4000,max_height=4000'],
            'color' => ['nullable', 'string', 'max:7', function ($attribute, $value, $fail) {
                if ($value !== '' && $value !== null && ! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    $fail('Color must be a valid hex code (e.g. #FF0000).');
                }
            }],
            'overlay_color' => ['nullable', 'string', 'max:7', function ($attribute, $value, $fail) {
                if ($value !== '' && $value !== null && ! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    $fail('Overlay color must be a valid hex code (e.g. #000000).');
                }
            }],
            'custom_html' => ['nullable', 'string', 'max:16000', new SafeHtml(SafeHtml::PROFILE_POPUP)],
            'url' => 'nullable|url|max:255',
            'cta_text' => 'nullable|string|max:255',
            'position' => 'sometimes|required|string|in:homepage,all_pages,product_page,category_page,cart,checkout',
            'size' => 'sometimes|required|string|in:small,medium,large',
            'type' => 'sometimes|required|string|in:image,solid_color,custom_html',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'show_close_button' => 'boolean',
            'enable_cookie' => 'boolean',
            'cookie_expiry_days' => 'nullable|integer|min:1|max:365',
            'delay_seconds' => 'nullable|integer|min:0|max:3600',
            'show_on_mobile' => 'boolean',
            'show_on_desktop' => 'boolean',
            'show_only_once' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Popup name is required.',
            'name.max' => 'Popup name must not exceed 255 characters.',
            'title.max' => 'Title must not exceed 255 characters.',
            'subtitle.max' => 'Subtitle must not exceed 255 characters.',
            'url.url' => 'URL must be a valid URL.',
            'url.max' => 'URL must not exceed 255 characters.',
            'cta_text.max' => 'CTA text must not exceed 255 characters.',
            'position.required' => 'Position is required.',
            'position.in' => 'Position must be one of: Homepage, All Pages, Product Page, Category Page, Cart, Checkout.',
            'size.required' => 'Size is required.',
            'size.in' => 'Size must be one of: Small, Medium, Large.',
            'type.required' => 'Type is required.',
            'type.in' => 'Type must be one of: Image, Solid Color, Custom HTML.',
            'starts_at.date' => 'Start date must be a valid date.',
            'ends_at.date' => 'End date must be a valid date.',
            'ends_at.after_or_equal' => 'End date must be on or after the start date.',
            'sort_order.integer' => 'Sort order must be an integer.',
            'sort_order.min' => 'Sort order must be at least 0.',
            'color.regex' => 'Color must be a valid hex code (e.g. #FF0000).',
            'overlay_color.regex' => 'Overlay color must be a valid hex code (e.g. #000000).',
            'image.image' => 'Image must be a valid image file.',
            'image.mimes' => 'Image must be in JPEG, PNG, JPG, GIF, or WebP format.',
            'image.mimetypes' => 'Image must be a JPEG, PNG, GIF or WebP file.',
            'image.max' => 'Image must not exceed 4MB.',
            'image.dimensions' => 'Image must be between 1x1 and 4000x4000 pixels.',
            'custom_html.max' => 'Custom HTML must not exceed 16000 characters.',
            'description.max' => 'Description must not exceed 2000 characters.',
            'cookie_expiry_days.integer' => 'Cookie expiry must be an integer.',
            'cookie_expiry_days.min' => 'Cookie expiry must be at least 1 day.',
            'cookie_expiry_days.max' => 'Cookie expiry must not exceed 365 days.',
            'delay_seconds.integer' => 'Delay must be an integer.',
            'delay_seconds.min' => 'Delay must be at least 0 seconds.',
            'delay_seconds.max' => 'Delay must not exceed 3600 seconds.',
        ];
    }
}
