<?php

namespace Tests\Feature;

use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\ApiEndpointParameter;
use App\Models\ApiToken;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Api\ApiEndpointTester;
use Database\Seeders\ApiManagementSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The definition "View" screen and the endpoint tester that runs from it.
 *
 * The tester is the interesting part: it dispatches a real request through
 * ApiAuthAndAuthorization and ApiDynamicController, so these tests assert on
 * the same status codes and bodies a live HTTP call would produce — without
 * needing the server, and without the Apache Authorization-header problem that
 * makes curl-based verification unreliable here.
 */
class ApiDefinitionViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(ApiManagementSeeder::class);

        $this->actingAs($this->admin(), 'admin');
    }

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'api-view-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        // The show/edit screens and the endpoint tester live behind
        // api_management.view / api_management.update (config/admin.php).
        // PermissionSeeder has already created the rows in this test's setUp,
        // so attach by name rather than inventing duplicates.
        foreach (['api_management.view', 'api_management.update'] as $name) {
            $admin->roles->first()->permissions()->attach(
                Permission::where('name', $name)->firstOrFail()->getKey()
            );
        }

        return $admin;
    }

    protected function definition(string $slug): ApiDefinition
    {
        return ApiDefinition::where('slug', $slug)->firstOrFail();
    }

    protected function endpoint(string $path): ApiEndpoint
    {
        return ApiEndpoint::where('path', $path)->firstOrFail();
    }

    // ---------------------------------------------------------------- show --

    public function test_the_show_screen_renders_the_definition(): void
    {
        $definition = $this->definition('storefront-data');

        $this->get(route('admin.api-management.definitions.show', $definition->getKey()))
            ->assertOk()
            ->assertSee($definition->name)
            ->assertSee($definition->slug)
            ->assertSee('Storefront Data');
    }

    public function test_the_show_screen_lists_endpoints_with_their_parameters(): void
    {
        $definition = $this->definition('storefront-data');

        $this->get(route('admin.api-management.definitions.show', $definition->getKey()))
            ->assertOk()
            // The full public URL, not just the stored path.
            ->assertSee('/store/products', escape: false)
            // Declared parameters are rendered, so an admin can see what the
            // endpoint accepts without opening its edit form.
            ->assertSee('per_page')
            ->assertSee('in_stock')
            ->assertSee('query');
    }

    public function test_the_show_screen_surfaces_permissions_and_auth_requirement(): void
    {
        $definition = $this->definition('admin-orders');

        $this->get(route('admin.api-management.definitions.show', $definition->getKey()))
            ->assertOk()
            ->assertSee('orders.view')
            ->assertSee('allow')
            ->assertSee('Auth required');
    }

    public function test_the_show_screen_is_not_reachable_anonymously(): void
    {
        auth()->guard('admin')->logout();

        $this->get(route('admin.api-management.definitions.show', $this->definition('storefront-data')->getKey()))
            ->assertRedirect();
    }

    public function test_the_index_links_to_the_show_screen(): void
    {
        $definition = $this->definition('storefront-data');

        $this->get(route('admin.api-management.definitions.index'))
            ->assertOk()
            ->assertSee(route('admin.api-management.definitions.show', $definition->getKey()), escape: false);
    }

    public function test_reseeding_revives_a_deleted_definition_instead_of_failing(): void
    {
        $definition = $this->definition('storefront-data');
        $definition->delete();

        $this->assertSoftDeleted('api_definitions', ['_id' => $definition->getKey()]);

        // A plain updateOrCreate cannot see the soft-deleted row and would try
        // to INSERT a duplicate slug, dying on the unique index. Re-running the
        // seeder has to restore the definition and its endpoints.
        $this->seed(ApiManagementSeeder::class);

        $this->assertDatabaseHas('api_definitions', [
            '_id' => $definition->getKey(),
            'slug' => 'storefront-data',
            'deleted_at' => null,
        ]);

        $this->assertSame(3, ApiEndpoint::where('api_definition_id', $definition->getKey())->count());
    }

    // -------------------------------------------------------------- tester --

    public function test_the_tester_runs_a_public_endpoint_and_returns_its_payload(): void
    {
        Product::factory()->create(['name' => 'Tester Tee', 'slug' => 'tester-tee']);

        $definition = $this->definition('storefront-data');
        $endpoint = $this->endpoint('store/products');

        $response = $this->post(
            route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]),
            ['query' => 'per_page=5']
        );

        $response->assertRedirect(route('admin.api-management.definitions.show', $definition->getKey()));

        $result = session('test_result');

        $this->assertSame(200, $result['response']['status'], 'Public endpoint must return 200.');
        $this->assertStringContainsString('Tester Tee', $result['response']['body']);
        // Query values arrive as strings, exactly as they would over HTTP.
        $this->assertSame('5', $result['request']['query']['per_page'] ?? null);
    }

    public function test_the_tester_reports_a_validation_failure_from_the_endpoint(): void
    {
        $definition = $this->definition('storefront-data');
        $endpoint = $this->endpoint('store/products');

        // sort is allow-listed by the endpoint's own rules, so the call is made
        // and refused by the API — which is exactly what the tester is for.
        $this->post(
            route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]),
            ['query' => 'sort=not-a-real-sort']
        );

        $result = session('test_result');

        $this->assertSame(422, $result['response']['status']);
        $this->assertStringContainsString('Validation failed', $result['response']['body']);
    }

    public function test_the_tester_substitutes_path_parameters(): void
    {
        Product::factory()->create(['name' => 'Path Tee', 'slug' => 'path-tee']);

        $definition = $this->definition('storefront-data');
        $endpoint = $this->endpoint('store/products/{product}');

        $this->post(
            route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]),
            ['path' => ['product' => 'path-tee']]
        );

        $result = session('test_result');

        $this->assertSame(200, $result['response']['status']);
        $this->assertStringContainsString('Path Tee', $result['response']['body']);
        $this->assertSame('path-tee', $result['request']['path_params']['product']);
    }

    public function test_the_tester_reports_a_404_for_an_unknown_product(): void
    {
        $definition = $this->definition('storefront-data');
        $endpoint = $this->endpoint('store/products/{product}');

        $this->post(
            route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]),
            ['path' => ['product' => 'nope']]
        );

        $result = session('test_result');

        $this->assertSame(404, $result['response']['status']);
        $this->assertStringContainsString('Product not found.', $result['response']['body']);
    }

    public function test_the_tester_enforces_authentication_on_a_protected_endpoint(): void
    {
        $definition = $this->definition('admin-orders');
        $endpoint = $this->endpoint('admin/orders');

        $this->post(route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]));

        $result = session('test_result');

        $this->assertSame(401, $result['response']['status']);
        $this->assertStringContainsString('UNAUTHORIZED', $result['response']['body']);
    }

    public function test_the_tester_succeeds_with_a_token_holding_the_permission(): void
    {
        Order::factory()->create(['order_number' => 'ORD-TESTER-1']);

        $token = $this->tokenForAdminWithOrdersView();

        $definition = $this->definition('admin-orders');
        $endpoint = $this->endpoint('admin/orders');

        $this->post(
            route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]),
            ['token' => $token]
        );

        $result = session('test_result');

        $this->assertSame(200, $result['response']['status'], 'A permitted token must reach the handler.');
        $this->assertStringContainsString('ORD-TESTER-1', $result['response']['body']);
    }

    public function test_the_tester_refuses_a_token_without_the_permission(): void
    {
        Order::factory()->create();

        $definition = $this->definition('admin-orders');
        $endpoint = $this->endpoint('admin/orders');

        $this->post(
            route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]),
            ['token' => $this->tokenForAdminWithOrdersView(grantPermission: false)]
        );

        $result = session('test_result');

        $this->assertSame(403, $result['response']['status']);
    }

    public function test_a_tester_run_is_written_to_the_route_log(): void
    {
        $definition = $this->definition('storefront-data');
        $endpoint = $this->endpoint('store/products');

        $this->post(route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]));

        $this->assertDatabaseHas('api_route_logs', [
            'api_endpoint_id' => $endpoint->getKey(),
            'method' => 'GET',
        ]);
    }

    public function test_the_tester_cannot_be_pointed_at_an_endpoint_from_another_definition(): void
    {
        $storefront = $this->definition('storefront-data');
        $adminDefinition = $this->definition('admin-orders');
        $storefrontEndpoint = $this->endpoint('store/products');

        // The endpoint belongs to storefront-data but is addressed through
        // admin-orders; this must not execute.
        $this->post(
            route('admin.api-management.endpoints.test', [$adminDefinition->getKey(), $storefrontEndpoint->getKey()])
        )->assertNotFound();

        $this->assertNull(session('test_result'));
    }

    public function test_the_tester_handles_an_endpoint_whose_handler_cannot_be_resolved(): void
    {
        // A handler_type the dispatcher does not understand resolves to null,
        // which the controller answers with a 500 error body rather than an
        // uncaught exception. The tester must show that body, not blow up.
        $definition = $this->definition('storefront-data');
        $endpoint = $this->endpoint('store/products');
        $endpoint->update(['action' => 'no_separator_here', 'handler_type' => 'controller']);

        $this->post(route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]));

        $result = session('test_result');

        $this->assertSame(500, $result['response']['status']);
        $this->assertStringContainsString('Handler not configured', $result['response']['body']);
    }

    // -------------------------------------------------------------- helpers --

    public function test_parse_key_value_lines_accepts_the_textarea_formats(): void
    {
        $tester = app(ApiEndpointTester::class);

        $this->assertSame(
            ['a' => '1', 'b' => 'two', 'c' => ''],
            $tester->parseKeyValueLines("a=1\nb=two\nc=")
        );

        $this->assertSame(['X-Header' => 'v'], $tester->parseKeyValueLines('X-Header: v'));

        // A bare key is a flag, so `in_stock` reads as `in_stock=1`.
        $this->assertSame(['in_stock' => '1'], $tester->parseKeyValueLines('in_stock'));

        $this->assertSame([], $tester->parseKeyValueLines("\n  \n"));
    }

    protected function tokenForAdminWithOrdersView(bool $grantPermission = true): string
    {
        $user = User::factory()->create(['is_active' => true]);

        Role::create(['name' => 'ApiTester', 'slug' => 'api-tester', 'description' => 'Test role']);
        $user->assignRole('ApiTester');

        if ($grantPermission) {
            $user->roles->first()->permissions()->attach(
                Permission::where('name', 'orders.view')->firstOrFail()->getKey()
            );
        }

        $plain = 'tester-token-'.ApiEndpointParameter::count().'-'.uniqid();

        ApiToken::create([
            'name' => 'tester',
            'user_id' => $user->getKey(),
            'token' => hash('sha256', $plain),
            'is_active' => true,
        ]);

        return $plain;
    }
}
