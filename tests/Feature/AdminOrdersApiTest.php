<?php

namespace Tests\Feature;

use App\Http\Middleware\ApiAuthAndAuthorization;
use App\Models\ApiEndpoint;
use App\Models\ApiToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Api\ApiRouteRegistrar;
use Database\Seeders\ApiManagementSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The admin definition is the authenticated half of the dynamic API: order
 * listing, order detail, token authentication and the orders.view grant.
 *
 * Registered through the real ApiAuthAndAuthorization middleware rather than
 * the bare `api` group the storefront tests use, because token handling is the
 * thing under test — a stubbed middleware would pass while the real one
 * rejected every request.
 */
class AdminOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // PermissionSeeder creates orders.view, which the seeder binds to the
        // admin endpoints, so it must run first.
        $this->seed(PermissionSeeder::class);
        $this->seed(ApiManagementSeeder::class);

        Route::middleware([ApiAuthAndAuthorization::class])
            ->prefix(config('api_management.prefix', 'api'))
            ->group(fn () => app(ApiRouteRegistrar::class)->register());
    }

    /**
     * Issue a token for a user, optionally holding a role.
     *
     * Only the 'Admin' role is given the orders.view grant — that is what
     * makes the "valid token, missing permission" case reachable. A null role
     * issues a token for a user with no role at all.
     */
    protected function tokenFor(?string $roleName = 'Admin'): string
    {
        $user = User::factory()->create(['is_active' => true]);

        if ($roleName !== null) {
            Role::create(['name' => $roleName, 'slug' => Str::slug($roleName)]);
            $user->assignRole($roleName);

            if ($roleName === 'Admin') {
                $user->roles->first()->permissions()->attach(
                    Permission::where('name', 'orders.view')->firstOrFail()->getKey()
                );
            }
        }

        $plain = 'plain-test-token-'.Str::uuid();

        ApiToken::create([
            'name' => 'test',
            'user_id' => $user->getKey(),
            'token' => hash('sha256', $plain),
            'is_active' => true,
        ]);

        return $plain;
    }

    public function test_an_anonymous_request_is_rejected(): void
    {
        $this->getJson('/api/admin/orders')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHORIZED');
    }

    public function test_a_valid_token_reaches_the_order_list(): void
    {
        Order::factory()->count(3)->create();

        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonStructure([
                'data' => ['items' => [['order_number', 'status', 'payment_status', 'totals' => ['grand_total']]]],
            ]);
    }

    public function test_a_token_without_the_permission_is_forbidden(): void
    {
        Order::factory()->create();

        // A valid token, but the role holds no orders.view grant.
        $this->withToken($this->tokenFor('Viewer'))
            ->getJson('/api/admin/orders')
            ->assertStatus(403);
    }

    public function test_an_inactive_token_is_rejected(): void
    {
        $plain = $this->tokenFor();

        ApiToken::query()->update(['is_active' => false]);

        $this->withToken($plain)->getJson('/api/admin/orders')->assertStatus(401);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $plain = $this->tokenFor();

        ApiToken::query()->update(['expires_at' => now()->subDay()]);

        $this->withToken($plain)->getJson('/api/admin/orders')->assertStatus(401);
    }

    public function test_the_order_list_can_be_filtered_by_status(): void
    {
        Order::factory()->create(['order_number' => 'ORD-A', 'status' => 'pending']);
        Order::factory()->create(['order_number' => 'ORD-B', 'status' => 'shipped']);

        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/orders?status=shipped')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.order_number', 'ORD-B');
    }

    public function test_an_unknown_status_is_rejected_by_validation(): void
    {
        Order::factory()->create();

        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/orders?status=teleported')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_the_order_list_is_paginated(): void
    {
        Order::factory()->count(4)->create();

        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/orders?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.pagination.total', 4)
            ->assertJsonPath('data.pagination.last_page', 2);
    }

    public function test_order_detail_is_served_by_order_number(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-DETAIL-1']);

        OrderItem::factory()->create([
            'order_id' => $order->getKey(),
            'product_name' => 'Wool Scarf',
            'quantity' => 2,
        ]);

        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/orders/ORD-DETAIL-1')
            ->assertOk()
            ->assertJsonPath('data.order_number', 'ORD-DETAIL-1')
            ->assertJsonPath('data.items.0.product_name', 'Wool Scarf')
            ->assertJsonStructure(['data' => ['items', 'addresses', 'status_history', 'customer']]);
    }

    public function test_order_detail_is_also_served_by_id(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-BY-ID']);

        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/orders/'.$order->getKey())
            ->assertOk()
            ->assertJsonPath('data.order_number', 'ORD-BY-ID');
    }

    public function test_an_unknown_order_is_a_404(): void
    {
        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/orders/ORD-NOPE')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Order not found.');
    }

    public function test_order_detail_also_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->getJson('/api/admin/orders/'.$order->getKey())->assertStatus(401);
    }

    public function test_the_line_item_snapshot_survives_a_deleted_product(): void
    {
        $product = Product::factory()->create(['name' => 'Original Name', 'slug' => 'original']);
        $order = Order::factory()->create(['order_number' => 'ORD-SNAPSHOT']);

        OrderItem::factory()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'product_name' => 'Original Name',
        ]);

        $product->delete();

        $this->withToken($this->tokenFor())
            ->getJson('/api/admin/orders/ORD-SNAPSHOT')
            ->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Original Name')
            // The live relation is gone, so the slug must be null, not an error.
            ->assertJsonPath('data.items.0.product_slug', null);
    }

    public function test_both_admin_endpoints_require_auth_and_carry_the_grant(): void
    {
        $endpoints = ApiEndpoint::whereIn('path', ['admin/orders', 'admin/orders/{order}'])->get();

        $this->assertCount(2, $endpoints);

        foreach ($endpoints as $endpoint) {
            $this->assertTrue($endpoint->requires_auth, "{$endpoint->path} must require auth.");
            $this->assertCount(1, $endpoint->permissions, "{$endpoint->path} must carry exactly one grant.");
            $this->assertSame('allow', $endpoint->permissions->first()->access_type);
        }
    }

    public function test_a_stale_endpoint_cache_entry_does_not_turn_a_401_into_a_500(): void
    {
        $endpoint = ApiEndpoint::where('path', 'admin/orders')->firstOrFail();

        // The middleware used to cache the Eloquent model itself. A cached
        // object of an unknown class unserialises to __PHP_Incomplete_Class
        // and blew up the `?ApiEndpoint` return type, so every protected
        // request 500'd instead of answering 401. The cache now holds a
        // boolean, so a hostile entry under the old key is inert.
        cache()->forever('api.endpoint.'.$endpoint->getKey(), 'not-a-model');

        $this->getJson('/api/admin/orders')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHORIZED');
    }

    public function test_repeated_requests_stay_authenticated(): void
    {
        $token = $this->tokenFor();
        Order::factory()->create();

        // The second call is served partly from cache; it must not degrade.
        foreach (range(1, 2) as $ignored) {
            $this->withToken($token)
                ->getJson('/api/admin/orders')
                ->assertOk()
                ->assertJsonCount(1, 'data.items');
        }
    }
}
