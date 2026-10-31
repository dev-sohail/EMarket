<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CouponService used to reject a coupon with a bare \Exception while every
 * caller caught only \InvalidArgumentException. An unknown or expired code
 * therefore escaped as a logged 500 ("Failed to apply coupon"), and a cart
 * whose saved coupon had since expired made the whole checkout page fail.
 */
class CouponRejectionTest extends TestCase
{
    use RefreshDatabase;

    private function cartWithProduct(): Cart
    {
        Setting::put('currency', 'GBP', 'display');
        setting_forget();

        $product = Product::factory()->create([
            'base_price' => 54.00,
            'sale_price' => null,
            'discount_value' => 0,
            'stock_status' => 'in_stock',
        ]);

        Inventory::factory()->withQuantity(10)->create([
            'product_id' => $product->getKey(),
            'variant_id' => null,
            'reserved_quantity' => 0,
        ]);

        // Pin the guest session by hand. JSON helpers drop cookies unless
        // withCredentials() is set, so without it the cart is never found.
        $sessionId = Str::random(40);
        $this->withCookie(config('session.cookie'), $sessionId)->withCredentials();

        $cart = Cart::factory()->forSession($sessionId)->create();
        CartItem::factory()->forCart($cart)->forProduct($product)->create(['quantity' => 1]);

        return $cart;
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ], $attributes));
    }

    public function test_an_unknown_code_is_reported_to_the_shopper_not_as_a_server_error(): void
    {
        $this->cartWithProduct();

        $this->postJson('/ajax/checkout/apply-coupon', ['coupon_code' => 'NOPE'])
            ->assertOk()
            ->assertJson(['success' => false, 'message' => 'Invalid coupon code.']);
    }

    public function test_an_expired_code_names_the_reason(): void
    {
        $this->cartWithProduct();
        $this->coupon(['expires_at' => now()->subDay()]);

        $this->postJson('/ajax/checkout/apply-coupon', ['coupon_code' => 'save10'])
            ->assertOk()
            ->assertJson(['success' => false, 'message' => 'This coupon has expired.']);
    }

    public function test_a_valid_code_returns_the_discount(): void
    {
        $this->cartWithProduct();
        $this->coupon();

        $this->postJson('/ajax/checkout/apply-coupon', ['coupon_code' => 'SAVE10'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.discount', 5.4);
    }

    public function test_a_missing_or_non_string_code_is_a_422_not_a_type_error(): void
    {
        $this->cartWithProduct();

        $this->postJson('/ajax/checkout/apply-coupon', [])->assertStatus(422);
        $this->postJson('/ajax/checkout/apply-coupon', ['coupon_code' => ['a']])->assertStatus(422);
        $this->postJson('/ajax/checkout/apply-coupon', ['coupon_code' => str_repeat('A', 51)])->assertStatus(422);
    }

    public function test_calculate_ignores_a_rejected_coupon_and_clears_it_from_the_cart(): void
    {
        $cart = $this->cartWithProduct();
        $cart->update(['coupon_code' => 'GONE']);

        $this->postJson('/ajax/checkout/calculate', ['coupon_code' => 'GONE'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.discount', 0);

        $this->assertNull($cart->fresh()->coupon_code);
    }

    public function test_the_checkout_page_survives_a_coupon_that_has_since_expired(): void
    {
        $cart = $this->cartWithProduct();
        $this->coupon(['expires_at' => now()->subDay()]);
        $cart->update(['coupon_code' => 'SAVE10']);

        ShippingMethod::factory()->create(['is_active' => true]);
        PaymentMethod::factory()->create(['code' => 'cod', 'is_cod' => true, 'is_active' => true]);

        $this->get(route('checkout'))->assertOk();

        $this->assertNull($cart->fresh()->coupon_code);
    }
}
