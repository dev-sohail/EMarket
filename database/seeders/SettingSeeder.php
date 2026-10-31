<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'general', 'key' => 'store_name', 'value' => 'Demo Store'],
            ['group' => 'general', 'key' => 'store_top_bar_message', 'value' => ''],
            ['group' => 'general', 'key' => 'store_email', 'value' => 'hello@example.com'],
            ['group' => 'general', 'key' => 'store_phone', 'value' => '+1 555 000 0000'],
            ['group' => 'general', 'key' => 'store_address', 'value' => '123 Example Street, Example City'],
            ['group' => 'general', 'key' => 'store_logo', 'value' => ''],
            ['group' => 'general', 'key' => 'favicon', 'value' => ''],
            ['group' => 'general', 'key' => 'timezone', 'value' => 'UTC'],
            ['group' => 'general', 'key' => 'default_country', 'value' => 'US'],
            ['group' => 'display', 'key' => 'currency', 'value' => 'USD'],

            ['group' => 'checkout', 'key' => 'guest_checkout', 'value' => 'true'],
            ['group' => 'checkout', 'key' => 'registration', 'value' => 'true'],
            ['group' => 'checkout', 'key' => 'email_verification', 'value' => 'false'],

            ['group' => 'order', 'key' => 'order_prefix', 'value' => 'ORD'],
            ['group' => 'order', 'key' => 'invoice_prefix', 'value' => 'INV'],

            ['group' => 'tax', 'key' => 'default_tax_rate', 'value' => '0'],

            ['group' => 'shipping', 'key' => 'default_shipping_cost', 'value' => '200'],

            ['group' => 'inventory', 'key' => 'low_stock_threshold', 'value' => '5'],
            ['group' => 'review', 'key' => 'reviews_enabled', 'value' => 'true'],
            ['group' => 'review', 'key' => 'returns_enabled', 'value' => 'false'],
            ['group' => 'blog', 'key' => 'blogs_enabled', 'value' => 'true'],

            ['group' => 'affiliate', 'key' => 'affiliate_enabled', 'value' => 'false'],
            ['group' => 'affiliate', 'key' => 'affiliate_commission_rate', 'value' => '5'],
            ['group' => 'affiliate', 'key' => 'affiliate_cookie_days', 'value' => '30'],
            ['group' => 'affiliate', 'key' => 'affiliate_min_payout', 'value' => '0'],
            ['group' => 'theme', 'key' => 'color_ink', 'value' => '#14171a'],
            ['group' => 'theme', 'key' => 'color_ink_soft', 'value' => '#40454b'],
            ['group' => 'theme', 'key' => 'color_paper', 'value' => '#faf9f6'],
            ['group' => 'theme', 'key' => 'color_paper_dim', 'value' => '#f0efe9'],
            ['group' => 'theme', 'key' => 'color_line', 'value' => '#dedcd4'],
            ['group' => 'theme', 'key' => 'color_steel', 'value' => '#6b6f76'],
            ['group' => 'theme', 'key' => 'color_red', 'value' => '#d6001c'],
            ['group' => 'theme', 'key' => 'color_red_dark', 'value' => '#a80016'],
            ['group' => 'theme', 'key' => 'color_sale', 'value' => '#1b7a43'],
        ];

        foreach ($settings as $setting) {
            Setting::put($setting['key'], $setting['value'], $setting['group']);
        }
    }
}
