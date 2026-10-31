<?php

namespace Tests\Feature;

use App\Http\Controllers\Store\FaqController;
use App\Http\Middleware\ApiAuthAndAuthorization;
use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\ApiEndpointParameter;
use App\Services\Api\ApiRouteRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Dynamic API endpoint lifecycle: definitions stored in the database become
 * real, callable routes at boot, and every stage of the request is enforced —
 * authentication, parameter validation, then RBAC/ABAC authorisation.
 *
 * These tests are the only reliable proof the registrar works. `route:list`
 * cannot be used for this: RouteListCommand replaces the router's route
 * collection with an empty CompiledRouteCollection before printing, so routes
 * added by a service provider disappear from its output even though they are
 * live for real HTTP requests.
 */
class DynamicApiRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ApiEndpoint::resolveHandler() only dispatches actions on the
        // configured allow-list, because an unrestricted `action` is arbitrary
        // method invocation inside the container. These tests point endpoints at
        // a real controller to prove dispatch works, so the fixture action is
        // allow-listed for the duration of each test rather than weakening the
        // shipped config.
        config([
            'api_management.allowed_handlers' => array_merge(
                config('api_management.allowed_handlers', []),
                [FaqController::class.'@index']
            ),
        ]);
    }

    /**
     * Register an endpoint and re-run the registrar, mirroring what happens at
     * boot. Returns the endpoint so the test can attach parameters.
     *
     * The application has already booted by the time RefreshDatabase has run
     * the migrations, so the boot-time registrar found no tables and stayed
     * quiet. Calling it again here is what a request-time route registration
     * would look like, which is exactly the behaviour under test.
     */
    protected function registerEndpoint(array $attributes = [], array $parameters = []): ApiEndpoint
    {
        $endpoint = ApiEndpoint::create(array_merge([
            'method' => 'GET',
            'path' => 'widgets',
            'action' => FaqController::class.'@index',
            'handler_type' => 'controller',
            'requires_auth' => false,
            'is_active' => true,
        ], $attributes));

        foreach ($parameters as $parameter) {
            ApiEndpointParameter::create(array_merge([
                'api_endpoint_id' => $endpoint->getKey(),
                'location' => 'query',
                'type' => 'string',
            ], $parameter));
        }

        $this->registerRoutes();

        return $endpoint;
    }

    /**
     * Re-run the registrar and re-apply the route group it registers into.
     *
     * ApiRouteRegistrar adds bare paths, so the configured prefix and
     * middleware group the provider normally wraps them in has to be
     * reproduced here for the route to resolve at its public URL.
     */
    protected function registerRoutes(): void
    {
        $registrar = app(ApiRouteRegistrar::class);

        Route::middleware([
            ApiAuthAndAuthorization::class,
        ])
            ->prefix(config('api-management.prefix', 'api'))
            ->group(function () use ($registrar) {
                $registrar->register();
            });
    }

    public function test_an_active_endpoint_becomes_a_routable_path(): void
    {
        $this->registerEndpoint(['path' => 'widgets']);

        $this->get('/api/widgets')
            ->assertOk();
    }

    public function test_an_inactive_endpoint_is_not_registered(): void
    {
        $this->registerEndpoint(['path' => 'widgets', 'is_active' => false]);

        $this->get('/api/widgets')
            ->assertNotFound();
    }

    public function test_a_required_parameter_must_be_present_and_valid(): void
    {
        $this->registerEndpoint(
            ['path' => 'widgets'],
            [['name' => 'search', 'required' => true, 'rules' => ['min:3']]],
        );

        // Missing the required parameter.
        $this->getJson('/api/widgets')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors' => ['search']]);

        // Present but too short.
        $this->getJson('/api/widgets?search=ab')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['search']]);

        // Valid: the handler runs and the call succeeds.
        $this->getJson('/api/widgets?search=abcd')
            ->assertOk();
    }

    public function test_an_endpoint_requiring_auth_rejects_anonymous_requests(): void
    {
        $this->registerEndpoint(['path' => 'widgets', 'requires_auth' => true]);

        $this->getJson('/api/widgets')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHORIZED');
    }

    public function test_every_call_is_written_to_the_route_log(): void
    {
        $endpoint = $this->registerEndpoint(['path' => 'widgets']);

        $this->getJson('/api/widgets')->assertOk();

        $this->assertDatabaseHas('api_route_logs', [
            'api_endpoint_id' => $endpoint->getKey(),
            'method' => 'GET',
            'path' => 'api/widgets',
        ]);
    }

    public function test_a_definition_is_the_unit_of_grouping_for_its_endpoints(): void
    {
        $definition = ApiDefinition::create([
            'name' => 'Widgets',
            'slug' => 'widgets',
            'source' => 'manual',
            'is_active' => true,
        ]);

        $endpoint = $this->registerEndpoint(['path' => 'widgets']);

        $endpoint->update(['api_definition_id' => $definition->getKey()]);

        $this->assertTrue($definition->endpoints->contains('_id', $endpoint->getKey()));
    }
}
