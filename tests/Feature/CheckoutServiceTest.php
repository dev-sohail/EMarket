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
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Adding to the cart reserves stock, so the shopper's own reservation is
 * already counted in `reserved_quantity` when they check out. Checkout used to
 * compare `quantity - reserved_quantity` with the line quantity, which counts
 * the reservation against the very customer who holds it: a customer could not
 * buy the last unit they had reserved, and needed twice the stock otherwise.
 */
class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shopper_can_buy_the_last_unit_they_reserved_in_their_cart(): void
    {
        [$product, $shippingMethod] = $this->fixtures(stock: 1);

        $sessionId = Str::random(40);
        $cart = Cart::factory()->forSession($sessionId)->create();

        app(CartService::class)->addItem($cart->getKey(), $product->getKey(), null, 1);

        $inventory = Inventory::where('product_id', $product->getKey())->first();
        $this->assertSame(1, $inventory->reserved_quantity);

        $this->withCookie(config('session.cookie'), $sessionId);
        $this->post(route('checkout.process'), $this->payload($shippingMethod));

        $order = Order::first();
        $this->assertNotNull($order, 'Checkout refused the shopper their own reservation: '.(string) session('error'));

        $inventory->refresh();
        $this->assertSame(0, $inventory->quantity);
        $this->assertSame(0, $inventory->reserved_quantity);
    }

    public function test_checkout_is_still_refused_when_physical_stock_is_short(): void
    {
        [$product, $shippingMethod] = $this->fixtures(stock: 1);

        $sessionId = Str::random(40);
        $cart = Cart::factory()->forSession($sessionId)->create();
        CartItem::factory()->forCart($cart)->forProduct($product)->create(['quantity' => 2]);

        $this->withCookie(config('session.cookie'), $sessionId);
        $this->post(route('checkout.process'), $this->payload($shippingMethod));

        $this->assertSame(0, Order::count());
        $inventory = Inventory::where('product_id', $product->getKey())->first();
        $this->assertSame(1, $inventory->quantity);
        $this->assertSame(0, $inventory->reserved_quantity);
    }

    public function test_a_replayed_submission_for_a_consumed_cart_returns_the_placed_order(): void
    {
        [$product, $shippingMethod] = $this->fixtures(stock: 5);

        $sessionId = Str::random(40);
        $cart = Cart::factory()->forSession($sessionId)->create();
        CartItem::factory()->forCart($cart)->forProduct($product)->create(['quantity' => 1]);

        $payload = $this->payload($shippingMethod);
        $this->withCookie(config('session.cookie'), $sessionId);
        $this->post(route('checkout.process'), $payload);

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertNull(Cart::find($cart->getKey()), 'The cart is consumed by the order.');

        // A second submission that was queued behind the cart lock arrives
        // after the cart is gone.
        $replayed = app(CheckoutService::class)->process([
            'cart_id' => $cart->getKey(),
            'idempotency_key' => $payload['idempotency_key'],
        ]);

        $this->assertSame($order->getKey(), $replayed->getKey());
        $this->assertSame(1, Order::count());
        $this->assertSame(4, Inventory::where('product_id', $product->getKey())->first()->quantity);
    }

    public function test_a_consumed_cart_without_a_matching_order_is_a_friendly_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(CheckoutService::class)->process([
            'cart_id' => (string) Str::uuid(),
            'idempotency_key' => 'never-used',
        ]);
    }

    /**
     * @return array{0: Product, 1: ShippingMethod}
     */
    protected function fixtures(int $stock): array
    {
        Setting::put('currency', 'GBP', 'display');
        setting_forget();

        $product = Product::factory()->create([
            'base_price' => 54.00,
            'sale_price' => null,
            'discount_value' => 0,
            'stock_status' => 'in_stock',
            'weight' => 0.5,
        ]);

        Inventory::factory()->withQuantity($stock)->create([
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
        ShippingZoneCity::create(['shipping_zone_id' => $zone->getKey(), 'city' => 'London']);
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
    protected function payload(ShippingMethod $shippingMethod): array
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
            'idempotency_key' => 'reservation-test-'.Str::random(8),
        ];
    }
}
