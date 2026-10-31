<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Cash on Delivery',
                'code' => 'cod',
                'is_cod' => true,
                'is_active' => true,
                'sort_order' => 0,
                'description' => 'Pay when you receive your order.',
            ],
            [
                'name' => 'Online',
                'code' => 'online',
                'is_cod' => false,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Pay securely online with Stripe.',
                // Read from config(), not env(): this project runs with
                // config:cache ON, so env() returns null here and the seeder
                // would overwrite the live payment method with Stripe disabled
                // and blank keys on every re-seed.
                'stripe_enabled' => (bool) config('services.stripe.enabled', false),
                'stripe_sandbox' => (bool) config('services.stripe.sandbox', true),
                'stripe_publishable_key' => config('services.stripe.publishable_key'),
                'stripe_secret_key' => config('services.stripe.secret_key'),
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
