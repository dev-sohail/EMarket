<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Shared\ImageCompression;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(protected ImageCompression $compression) {}

    public function index()
    {
        $settings = Setting::all()->keyBy('key')->mapWithKeys(fn ($item) => [$item->key => $item->value]);

        // Ensure theme color defaults are always present
        $defaultColors = [
            'color_ink' => '#14171a',
            'color_ink_soft' => '#40454b',
            'color_paper' => '#faf9f6',
            'color_paper_dim' => '#f0efe9',
            'color_line' => '#dedcd4',
            'color_steel' => '#6b6f76',
            'color_red' => '#d6001c',
            'color_red_dark' => '#a80016',
            'color_sale' => '#1b7a43',
        ];

        foreach ($defaultColors as $key => $default) {
            $settings[$key] = $settings[$key] ?? $default;
        }

        return view('admin.setting.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'store_name' => 'nullable|string|max:255',
            'store_tagline' => 'nullable|string|max:255',
            'store_description' => 'nullable|string',
            'store_top_bar_message' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:100',
            'store_email' => 'nullable|email',
            'store_phone' => 'nullable|string|max:20',
            'store_address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'default_country' => 'nullable|string|max:100',
            'whatsapp' => 'nullable|string|max:20',
            'business_hours' => 'nullable|string|max:255',
            'store_logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'favicon' => 'nullable|image|mimes:ico,png,jpg,webp|max:500',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:500',
            'order_prefix' => 'nullable|string|max:20',
            'invoice_prefix' => 'nullable|string|max:20',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'default_shipping_cost' => 'nullable|numeric|min:0',
            'default_tax_rate' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'products_per_page' => 'nullable|integer|min:1|max:100',
            'facebook_url' => 'nullable|url',
            'instagram_url' => 'nullable|url',
            'twitter_url' => 'nullable|url',
            'youtube_url' => 'nullable|url',
            'tiktok_url' => 'nullable|url',
            'color_ink' => 'nullable|string|max:7',
            'color_ink_soft' => 'nullable|string|max:7',
            'color_paper' => 'nullable|string|max:7',
            'color_paper_dim' => 'nullable|string|max:7',
            'color_line' => 'nullable|string|max:7',
            'color_steel' => 'nullable|string|max:7',
            'color_red' => 'nullable|string|max:7',
            'color_red_dark' => 'nullable|string|max:7',
            'color_sale' => 'nullable|string|max:7',
            'affiliate_enabled' => 'nullable|boolean',
            'guest_checkout' => 'nullable|boolean',
            'registration' => 'nullable|boolean',
            'email_verification' => 'nullable|boolean',
            'reviews_enabled' => 'nullable|boolean',
            'returns_enabled' => 'nullable|boolean',
            'blogs_enabled' => 'nullable|boolean',
        ]);

        $groupMap = [
            'store_name' => 'general',
            'store_tagline' => 'general',
            'store_description' => 'general',
            'store_top_bar_message' => 'general',
            'timezone' => 'general',
            'store_email' => 'general',
            'store_phone' => 'general',
            'store_address' => 'general',
            'city' => 'general',
            'country' => 'general',
            'default_country' => 'general',
            'whatsapp' => 'general',
            'business_hours' => 'general',
            'meta_title' => 'seo',
            'meta_description' => 'seo',
            'meta_keywords' => 'seo',
            'order_prefix' => 'order',
            'invoice_prefix' => 'order',
            'low_stock_threshold' => 'inventory',
            'default_shipping_cost' => 'shipping',
            'default_tax_rate' => 'tax',
            'currency' => 'display',
            'products_per_page' => 'display',
            'facebook_url' => 'social',
            'instagram_url' => 'social',
            'twitter_url' => 'social',
            'youtube_url' => 'social',
            'tiktok_url' => 'social',
            'color_ink' => 'theme',
            'color_ink_soft' => 'theme',
            'color_paper' => 'theme',
            'color_paper_dim' => 'theme',
            'color_line' => 'theme',
            'color_steel' => 'theme',
            'color_red' => 'theme',
            'color_red_dark' => 'theme',
            'color_sale' => 'theme',
        ];

        try {
            $textFields = collect($validated)->except([
                'store_logo',
                'favicon',
                'affiliate_enabled',
                'guest_checkout',
                'registration',
                'email_verification',
                'reviews_enabled',
                'returns_enabled',
                'blogs_enabled',
            ]);

            foreach ($textFields as $key => $value) {
                Setting::put($key, $value, $groupMap[$key] ?? 'general');
            }

            foreach (['guest_checkout', 'registration', 'email_verification', 'reviews_enabled', 'returns_enabled'] as $toggle) {
                Setting::put(
                    $toggle,
                    $request->boolean($toggle) ? '1' : '0',
                    $toggle === 'reviews_enabled' || $toggle === 'returns_enabled' ? 'review' : 'checkout',
                );
            }

            Setting::put('blogs_enabled', $request->boolean('blogs_enabled') ? '1' : '0', 'blog');

            Setting::put('affiliate_enabled', $request->boolean('affiliate_enabled') ? '1' : '0', 'affiliate');

            if ($request->hasFile('store_logo')) {
                $path = $this->compression->compress($request->file('store_logo'), 400, 85)->store('settings', 'public');
                Setting::put('store_logo', $path);
            }

            if ($request->hasFile('favicon')) {
                $path = $request->file('favicon')->store('settings', 'public');
                Setting::put('favicon', $path);
            }
        } finally {
            setting_forget();
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
