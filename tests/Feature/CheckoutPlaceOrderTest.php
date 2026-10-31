<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use App\Models\ShippingZoneCity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The whole checkout, end to end, as a guest shopper posts it.
 *
 * There was no test that placed an order, so two separate faults could sit on
 * this path unnoticed:
 *
 *  - `CheckoutService::process()` takes a `Cache::lock()` on the cart before it
 *    does anything. The `cache_locks` table rejected every insert (see
 *    CacheLockSchemaTest), so `block(30)` burned 30 seconds and then threw, and
 *    the customer got the generic "We encountered an error while processing your
 *    order" page on every attempt.
 *  - a shipping rule whose `price` is 0.00 is not applied (ShippingRule::
 *    calculateRate documents this), so a zone whose only rule is priced 0.00
 *    offers no method at all and checkout refuses the order.
 *
 * This test pins the happy path, so either regression fails loudly here instead
 * of at the customer's card.
 */
class CheckoutPlaceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_place_an_order_end_to_end(): void
    {
        [$product, $shippingMethod] = $this->purchasableCartFixtures();

        // A guest cart is resolved by the session the request carries. Test
        // requests do not inherit cookies from earlier requests, so the session
        // cookie is pinned by hand to a valid 40-character id (see
        // Store::isValidId); otherwise every request mints a new session and the
        // cart is never found.
        $sessionId = Str::random(40);
        $this->withCookie(config('session.cookie'), $sessionId);

        $cart = Cart::factory()->forSession($sessionId)->create();
        CartItem::factory()->forCart($cart)->forProduct($product)->create(['quantity' => 1]);

        $response = $this->post(route('checkout.process'), $this->checkoutPayload($shippingMethod));

        $this->assertNotSame(
            500,
            $response->getStatusCode(),
            'Checkout must not fail: '.(string) session('error')
        );

        $order = Order::with('items')->first();

        $this->assertNotNull(
            $order,
            'No order was created. The checkout page reported: '.(string) session('error')
        );

        $this->assertSame(54.00, (float) $order->subtotal);
        $this->assertSame(4.99, (float) $order->shipping_cost);
        $this->assertSame(58.99, (float) $order->grand_total);
        $this->assertSame('London', $order->shippingAddress->city);
        $this->assertCount(1, $order->items);
    }

    /**
     * A product with stock on hand, a shipping zone covering the destination city
     * and a method that is actually priced for it, and an offline payment method.
     *
     * @return array{0: Product, 1: ShippingMethod}
     */
    protected function purchasableCartFixtures(): array
    {
        // CheckoutService refuses to write an order when `currency()` is not a
        // three letter code, and `currency()` is a setting.
        Setting::put('currency', 'GBP', 'display');
        setting_forget();

        $product = Product::factory()->create([
            'base_price' => 54.00,
            'sale_price' => null,
            'discount_value' => 0,
            'stock_status' => 'in_stock',
            'weight' => 0.5,
        ]);

        Inventory::factory()->withQuantity(10)->create([
            'product_id' => $product->getKey(),
            'variant_id' => null,
            'reserved_quantity' => 0,
        ]);

        $shippingMethod = ShippingMethod::factory()->create([
            'name' => 'Standard',
            'base_cost' => 0,
            'per_kg_cost' => 0,
            'estimated_days' => 5,
            'is_active' => true,
        ]);

        $zone = ShippingZone::create(['name' => 'Standard', 'is_active' => true, 'sort_order' => 0]);

        ShippingZoneCity::create([
            'shipping_zone_id' => $zone->getKey(),
            'city' => 'London',
        ]);

        // price must be > 0: ShippingRule::calculateRate treats a 0.00 price as
        // "this rule does not apply" rather than "shipping is free".
        ShippingRule::create([
            'shipping_zone_id' => $zone->getKey(),
            'shipping_method_id' => $shippingMethod->getKey(),
            'price' => 4.99,
            'is_active' => true,
        ]);

        PaymentMethod::factory()->create([
            'name' => 'Cash on Delivery',
            'code' => 'cod',
            'is_cod' => true,
            'is_active' => true,
        ]);

        return [$product, $shippingMethod];
    }

    /**
     * @return array<string, string>
     */
    protected function checkoutPayload(ShippingMethod $shippingMethod): array
    {
        return [
            'name' => 'Guest Shopper',
            'email' => 'guest-shopper@example.test',
            'phone' => '+441234567890',
            'shipping_use_new_address' => '1',
            'shipping_first_name' => 'Guest',
            'shipping_last_name' => 'Shopper',
            'shipping_address' => '1 Test Street',
            'shipping_city' => 'London',
            'shipping_state' => 'London',
            'shipping_zip_code' => 'SW1A 1AA',
            'shipping_phone' => '+441234567890',
            'shipping_method_id' => $shippingMethod->getKey(),
            'payment_method' => 'cod',
            'idempotency_key' => 'checkout-place-order-test',
        ];
    }
}
