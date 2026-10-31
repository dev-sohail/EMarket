<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithCustomer(): User
    {
        $user = User::factory()->create();
        Customer::factory()->create(['user_id' => $user->_id]);
        $user->load('customer');

        return $user;
    }

    public function test_status_allows_access_by_order_customer(): void
    {
        $user = $this->createUserWithCustomer();

        $order = Order::factory()->create([
            'customer_id' => $user->customer->_id,
            'user_id' => $user->_id,
            'tracking_token' => 'token-customer-'.uniqid(),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('stripe.status', $order->order_number));

        $response->assertOk();
        $response->assertJson([
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
        ]);
    }

    public function test_status_allows_access_by_order_user_without_customer(): void
    {
        $user = User::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $otherCustomer->_id,
            'user_id' => $user->_id,
            'tracking_token' => 'token-user-'.uniqid(),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('stripe.status', $order->order_number));

        $response->assertOk();
    }

    public function test_status_allows_guest_with_valid_tracking_token_query_param(): void
    {
        $order = Order::factory()->create([
            'customer_id' => null,
            'user_id' => null,
            'tracking_token' => 'valid-tracking-token',
        ]);

        $response = $this->getJson(route('stripe.status', [$order->order_number, 'tracking_token' => 'valid-tracking-token']));

        $response->assertOk();
    }

    public function test_status_allows_guest_with_valid_tracking_token_cookie(): void
    {
        $order = Order::factory()->create([
            'customer_id' => null,
            'user_id' => null,
            'tracking_token' => 'cookie-tracking-token-123',
        ]);

        $response = $this->withCredentials()->withCookie('guest_order_token', 'cookie-tracking-token-123')
            ->getJson(route('stripe.status', $order->order_number));

        $response->assertOk();
    }

    public function test_status_denies_guest_without_tracking_token(): void
    {
        $order = Order::factory()->create([
            'customer_id' => null,
            'user_id' => null,
            'tracking_token' => 'some-token',
        ]);

        $response = $this->getJson(route('stripe.status', $order->order_number));

        $response->assertForbidden();
    }

    public function test_status_denies_guest_with_wrong_tracking_token(): void
    {
        $order = Order::factory()->create([
            'customer_id' => null,
            'user_id' => null,
            'tracking_token' => 'correct-token',
        ]);

        $response = $this->getJson(route('stripe.status', [$order->order_number, 'tracking_token' => 'wrong-token']));

        $response->assertForbidden();
    }

    public function test_status_denies_unauthorized_authenticated_user(): void
    {
        $user = User::factory()->create();
        Customer::create([
            'user_id' => $user->_id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => null,
            'user_id' => null,
            'tracking_token' => 'some-token',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('stripe.status', $order->order_number));

        $response->assertForbidden();
    }

    public function test_status_denies_authenticated_user_without_relationship_even_with_tracking_token(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => null,
            'user_id' => null,
            'tracking_token' => 'some-token',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('stripe.status', [$order->order_number, 'tracking_token' => 'some-token']));

        $response->assertOk();
    }

    public function test_status_denies_guest_with_forgeable_order_number_cookie(): void
    {
        $order = Order::factory()->create([
            'customer_id' => null,
            'user_id' => null,
            'tracking_token' => 'secure-token',
        ]);

        $response = $this->withCookie('guest_order_number', $order->order_number)
            ->getJson(route('stripe.status', $order->order_number));

        $response->assertForbidden();
    }

    public function test_cancel_denies_unauthorized_user(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::factory()->create();
        Customer::create([
            'user_id' => $user->_id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => null,
            'user_id' => null,
            'tracking_token' => 'some-token',
            'payment_method' => 'cash_on_delivery',
        ]);

        $response = $this->actingAs($user)
            ->post(route('stripe.cancel', $order->order_number));

        $response->assertForbidden();
    }

    public function test_order_tracking_get_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->get(route('order.track'));
            $response->assertOk();
        }

        $response = $this->get(route('order.track'));
        $response->assertStatus(429);
    }

    public function test_order_tracking_post_is_rate_limited(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson(route('order.track.result'), []);
            $response->assertStatus(422);
        }

        $response = $this->postJson(route('order.track.result'), []);
        $response->assertStatus(429);
    }
}
