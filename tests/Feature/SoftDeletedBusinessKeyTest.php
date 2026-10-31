<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A soft delete writes `deleted_at` and leaves the row in place, so it keeps
 * holding the business key it was registered under. Every lookup in this
 * application is soft-delete-scoped, so the row is invisible, the value looks
 * free, and the following insert dies on the plain unique index instead of
 * producing a validation message.
 *
 * The policy the two sides now agree on, and what these tests pin down:
 *
 *  - `customers` — reuse and RESTORE the trashed row. The same policy
 *    `CheckoutService::resolveGuestCustomer()` applies to a returning guest; the
 *    row carries the order history being linked and no credentials.
 *  - `users` — REFUSE. A user row carries `roles` and `email_verified_at`, so
 *    resurrecting a deleted account from a public registration form would hand
 *    the deleted account's permissions to whoever knows the address. This also
 *    matches every `unique:users,email` rule in the application, which query
 *    through the query builder and so already see trashed rows.
 *  - `orders.order_number` — never reuse, and never reissue. The index stays
 *    plain because an order number is a public identifier, so the generator has
 *    to count over every row instead.
 */
class SoftDeletedBusinessKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_refuses_a_soft_deleted_users_email(): void
    {
        $deleted = User::factory()->create(['email' => 'gone@example.com']);
        $deleted->delete();

        $response = $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'gone@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertSessionHas(
            'error',
            fn (?string $message) => $message !== null && str_contains($message, 'deactivated account')
        );
        $this->assertGuest('web');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'gone@example.com', 'deleted_at' => null]);
        $this->assertSoftDeleted('users', ['_id' => $deleted->getKey()]);
    }

    public function test_registration_restores_a_soft_deleted_guest_customer(): void
    {
        $guest = Customer::create([
            'user_id' => null,
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.com',
            'phone' => '+41790000000',
        ]);
        $guest->delete();

        $response = $this->post(route('register.store'), [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'phone' => '+41790000000',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            '_id' => $guest->getKey(),
            'deleted_at' => null,
        ]);

        $user = User::where('email', 'grace@example.com')->sole();

        $this->assertDatabaseHas('customers', [
            '_id' => $guest->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_registration_refuses_a_soft_deleted_customer_still_linked_to_a_live_account(): void
    {
        $user = User::factory()->create(['email' => 'linked@example.com']);

        $customer = Customer::create([
            'user_id' => $user->getKey(),
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'linked@example.com',
        ]);
        $customer->delete();

        $response = $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'linked@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertSessionHas(
            'error',
            fn (?string $message) => $message !== null && str_contains($message, 'already exists')
        );
        $this->assertGuest('web');

        $this->assertDatabaseCount('users', 1);
        $this->assertSoftDeleted('customers', ['_id' => $customer->getKey()]);
    }

    public function test_the_next_order_number_is_issued_past_a_soft_deleted_highest_order(): void
    {
        $year = date('Y');

        $this->orderNumbered(sprintf('ORD-%s-%06d', $year, 1));
        $this->orderNumbered(sprintf('ORD-%s-%06d', $year, 2))->delete();

        $this->assertSame(
            sprintf('ORD-%s-%06d', $year, 3),
            Order::generateOrderNumber(),
            'A trashed order still occupies its number, so the sequence must include it.'
        );

        $placed = Order::create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $this->assertSame(sprintf('ORD-%s-%06d', $year, 3), $placed->order_number);
        $this->assertMatchesRegularExpression(
            '/^ORD-'.preg_quote($year, '/').'-\d{6}$/',
            $placed->order_number,
            'The published order-number format must not change.'
        );
    }

    public function test_the_next_order_number_sorts_numerically_past_six_digits(): void
    {
        $year = date('Y');

        $this->orderNumbered('ORD-'.$year.'-999999');
        $this->orderNumbered('ORD-'.$year.'-1000000');

        $this->assertSame(
            'ORD-'.$year.'-1000001',
            Order::generateOrderNumber(),
            'ORD-'.$year.'-1000000 sorts below ORD-'.$year.'-999999 as a string, so the scan must not sort on the number alone.'
        );
    }

    /**
     * Create an order with a number supplied by the caller, so the generator is
     * bypassed and only the sequence scan is under test.
     */
    private function orderNumbered(string $orderNumber): Order
    {
        return Order::create([
            'order_number' => $orderNumber,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'subtotal' => 100,
            'grand_total' => 100,
        ]);
    }
}
