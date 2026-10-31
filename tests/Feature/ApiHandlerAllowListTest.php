<?php

namespace Tests\Feature;

use App\Http\Controllers\Store\FaqController;
use App\Models\ApiEndpoint;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Api\StoreCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ApiEndpoint::resolveHandler() used to take the `action` string and hand it
 * straight to app(), then call any public method found on the result. `action`
 * is a plain validated string on StoreApiEndpointRequest, so any admin who could
 * create an endpoint could point it at any class in the container and any method
 * on it — arbitrary method invocation behind an admin login.
 *
 * These tests pin the allow-list that closes it.
 */
class ApiHandlerAllowListTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Test']);

        foreach (['api.manage', 'audit_logs.view'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'api',
            ])->getKey());
        }

        $admin->assignRole('Super Admin');

        return $admin;
    }

    protected function endpoint(string $action, string $handlerType = 'service'): ApiEndpoint
    {
        return ApiEndpoint::create([
            'method' => 'GET',
            'path' => 'probe',
            'action' => $action,
            'handler_type' => $handlerType,
            'requires_auth' => false,
            'is_active' => true,
        ]);
    }

    public function test_an_action_outside_the_allow_list_resolves_to_nothing(): void
    {
        $endpoint = $this->endpoint('App\Models\User::delete');

        $this->assertNull(
            $endpoint->resolveHandler(),
            'A handler outside the allow-list must not be instantiated, let alone invoked.'
        );
    }

    public function test_a_known_container_class_with_a_dangerous_method_is_refused(): void
    {
        // Both of these are real, resolvable classes in the container. The only
        // thing stopping them is the allow-list, which is the point.
        $this->assertNull($this->endpoint('App\Models\User::truncate')->resolveHandler());
        $this->assertNull($this->endpoint('Illuminate\Support\Facades\Artisan::call')->resolveHandler());
    }

    public function test_an_allowed_class_paired_with_an_unreviewed_method_is_refused(): void
    {
        config([
            'api_management.allowed_handlers' => [
                'App\Services\Api\StoreCatalogService::products',
            ],
        ]);

        // The class is on the list, but `filters` is not, so the exact-string
        // check refuses it. A blanket class-level entry would not.
        $this->assertNull(
            $this->endpoint('App\Services\Api\StoreCatalogService::filters')->resolveHandler()
        );
    }

    public function test_an_allowed_handler_still_resolves(): void
    {
        config([
            'api_management.allowed_handlers' => [
                'App\Services\Api\StoreCatalogService::products',
            ],
        ]);

        $this->assertInstanceOf(
            StoreCatalogService::class,
            $this->endpoint('App\Services\Api\StoreCatalogService::products')->resolveHandler()
        );
    }

    public function test_a_controller_handler_on_the_allow_list_resolves(): void
    {
        config(['api_management.allowed_handlers' => [FaqController::class.'@index']]);

        $this->assertInstanceOf(
            FaqController::class,
            $this->endpoint(FaqController::class.'@index', 'controller')->resolveHandler()
        );
    }

    public function test_a_missing_allow_list_denies_everything_rather_than_allowing_it(): void
    {
        // Fails closed: a typo in config, or a cached config from an older
        // deploy that predates the key, must not restore arbitrary dispatch.
        config(['api_management.allowed_handlers' => null]);

        $this->assertNull($this->endpoint('App\Models\User::delete')->resolveHandler());
    }

    public function test_the_shipped_allow_list_only_names_read_only_api_services(): void
    {
        $configured = config('api_management.allowed_handlers');

        $this->assertIsArray($configured);
        $this->assertNotEmpty($configured, 'The shipped allow-list must not be empty.');

        foreach ($configured as $action) {
            $this->assertStringStartsWith(
                'App\Services\Api\\',
                $action,
                'Only the purpose-built App\Services\Api readers may be dispatchable. '
                ."Found: {$action}"
            );
        }
    }

    public function test_the_admin_api_management_screens_require_authentication(): void
    {
        // The allow-list is the boundary; the screens that choose the action are
        // the other half, so confirm they are not anonymous.
        foreach (['admin.api-management.definitions.index'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route {$routeName} should exist.");
            $this->assertContains(
                'auth:admin',
                $route->gatherMiddleware(),
                "{$routeName} must sit behind the admin guard."
            );
        }
    }
}
