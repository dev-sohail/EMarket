<?php

use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\ApiEndpointParameter;
use App\Models\ApiEndpointPermission;
use App\Models\ApiRouteLog;
use App\Models\ApiToken;
use App\Models\Permission;
use App\Models\PrefixedModel;
use App\Models\Role;

return [
    /*
    |--------------------------------------------------------------------------
    | API Management Module
    |--------------------------------------------------------------------------
    |
    | Configuration for the dynamic Admin API Management module. The module
    | registers API endpoints from database definitions at application boot
    | and dispatches them through a single generic controller.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix applied to all dynamically registered API routes.
    |
    */

    'prefix' => env('API_MANAGEMENT_PREFIX', 'api'),

    /*
    |--------------------------------------------------------------------------
    | Allowed Endpoint Handlers
    |--------------------------------------------------------------------------
    |
    | An endpoint's `action` is a `Class@method` (controller) or `Class::method`
    | (service) string that ApiEndpoint::resolveHandler() passes to app() and
    | then invokes. Without a restriction here that string is arbitrary code
    | execution: any admin who can create an endpoint can point it at any class
    | in the container and any public method on it.
    |
    | Only handlers listed here may be dispatched. Entries are exact, fully
    | qualified, and matched against the action string as written, so a new
    | endpoint cannot widen its own reach by naming a different method on an
    | allowed class.
    |
    | Wildcards are deliberately NOT supported. A `App\Services\*::*` style
    | pattern would hand back the original hole, since any service added later
    | would become callable by default.
    |
    | Add an entry when you genuinely want a new dynamic endpoint, and keep it
    | to the narrowest method that does the job.
    |
    */

    'allowed_handlers' => [
        // The handlers the api_endpoints table currently holds. Each is a
        // read-only catalog/orders reader reached through
        // ApiDynamicController, which passes the Request as the only argument.
        'App\Services\Api\StoreCatalogService::products',
        'App\Services\Api\StoreCatalogService::product',
        'App\Services\Api\StoreCatalogService::filters',
        'App\Services\Api\AdminOrdersService::orders',
        'App\Services\Api\AdminOrdersService::order',

        // What DefinitionReflector writes when it generates a definition from a
        // model, so a reflected endpoint is servable without an admin having to
        // edit config. ReflectedCatalogService takes the model from the request's
        // api_endpoint attribute rather than from user input, and both methods
        // only ever read.
        'App\Services\Api\ReflectedCatalogService::index',
        'App\Services\Api\ReflectedCatalogService::show',
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Storage
    |--------------------------------------------------------------------------
    |
    | The database table and model used to store API tokens. Override these if
    | you are using Laravel Sanctum, Passport, or a custom token provider.
    |
    */

    'tokens_table' => env('API_TOKENS_TABLE', 'api_tokens'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Default rate limit applied to dynamic API endpoints unless overridden
    | per-endpoint in the management UI.
    |
    */

    'rate_limit' => [
        'attempts' => env('API_RATE_LIMIT_ATTEMPTS', 60),
        'decay_seconds' => env('API_RATE_LIMIT_DECAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Whether to log API requests to the api_route_logs table for audit and
    | debugging purposes. When disabled no row is written for either a
    | success or an error response.
    |
    */

    'logging' => [
        'enabled' => env('API_LOGGING_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Discovery / Reflection
    |--------------------------------------------------------------------------
    |
    | When enabled, the module can introspect Eloquent models and existing
    | service classes to auto-generate API definitions. This drives the
    | `api:discover` artisan command.
    |
    */

    'discovery' => [
        /*
        | Paths that will be scanned for Eloquent models. Each matching model
        | class is offered as a candidate API definition.
        */
        'model_paths' => [
            app_path('Models'),
        ],

        /*
        | Patterns of model classes to exclude from auto-discovery.
        */
        'exclude_models' => [
            ApiDefinition::class,
            ApiEndpoint::class,
            ApiEndpointParameter::class,
            ApiEndpointPermission::class,
            ApiToken::class,
            ApiRouteLog::class,
            Role::class,
            Permission::class,
            PrefixedModel::class,
        ],
    ],
];
