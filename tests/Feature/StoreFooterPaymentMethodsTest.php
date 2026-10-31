<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the storefront footer's payment block.
 *
 * It used to be a hardcoded marketing claim: a literal "COD" badge, the words
 * "Pay when you receive", and a sentence interpolating `default_country`.
 *
 * That made the footer contradict the admin. Deactivating the `cod` method left
 * the storefront still promising Cash on Delivery, while checkout — which only
 * offers PaymentMethod::active() — no longer accepted it. The country sentence
 * was worse than cosmetic: default_country holds a two-letter code, so it
 * rendered "across GB", and it implied COD availability was decided by that
 * setting, which nothing enforces.
 *
 * @see resources/views/components/store/footer.blade.php
 */
class StoreFooterPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function cod(array $attributes = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'name' => 'Cash on Delivery',
            'code' => 'cod',
            'description' => 'Pay when you receive your order.',
            'is_cod' => true,
            'is_active' => false,
            'sort_order' => 0,
        ], $attributes));
    }

    private function online(array $attributes = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'name' => 'Online',
            'code' => 'online',
            'description' => 'Pay securely online with Stripe.',
            'is_cod' => false,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    public function test_an_inactive_cod_method_is_not_advertised(): void
    {
        $this->cod();
        $this->online();

        $footer = $this->footer();

        $this->assertStringNotContainsString(
            'COD',
            $footer,
            'The footer must not advertise Cash on Delivery while the cod method is inactive.'
        );
        $this->assertStringNotContainsString(
            'Pay when you receive your order.',
            $footer,
            'An inactive method\'s description must not leak into the footer.'
        );
        $this->assertStringContainsString('ONLINE', $footer);
    }

    public function test_an_active_cod_method_is_advertised(): void
    {
        $this->cod(['is_active' => true]);
        $this->online();

        $footer = $this->footer();

        $this->assertStringContainsString('COD', $footer);
        $this->assertStringContainsString('Pay when you receive your order.', $footer);
        $this->assertStringContainsString('ONLINE', $footer);
    }

    public function test_only_active_methods_are_listed(): void
    {
        $this->cod();
        $this->online();

        $footer = $this->footer();

        $this->assertSame(
            1,
            substr_count($footer, 'Pay securely online with Stripe.'),
            'Exactly the one active method should be described.'
        );
        $this->assertStringNotContainsString('across', $footer);
    }

    public function test_an_active_cod_method_renders_before_an_active_online_method(): void
    {
        $this->cod(['is_active' => true, 'sort_order' => 0]);
        $this->online(['sort_order' => 1]);

        $footer = $this->footer();

        $this->assertLessThan(
            strpos($footer, 'Pay securely online with Stripe.'),
            strpos($footer, 'Pay when you receive your order.'),
            'Methods must be listed in sort_order, so the footer matches the checkout order.'
        );
    }

    public function test_the_footer_says_so_when_no_method_is_active(): void
    {
        $this->cod();
        $this->online(['is_active' => false]);

        $footer = $this->footer();

        $this->assertStringContainsString('No payment method is available online right now.', $footer);
        $this->assertStringNotContainsString('COD', $footer);
        $this->assertStringNotContainsString('ONLINE', $footer);
    }

    public function test_the_footer_never_renders_the_raw_country_code(): void
    {
        Setting::put('default_country', 'GB');
        $this->online();

        $footer = $this->footer();

        $this->assertStringNotContainsString(
            'across GB',
            $footer,
            'default_country is a code, not a name; the country sentence is gone.'
        );
    }

    private function footer(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }
}
