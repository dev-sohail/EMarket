<?php

namespace App\Providers;

use App\Http\Middleware\ApiAuthAndAuthorization;
use App\Services\Api\ApiRouteRegistrar;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the dynamic Admin API Management module.
 *
 * Wires route registration, config publishing, and middleware aliasing into
 * the Laravel lifecycle.
 */
class ApiManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/api_management.php',
            'api_management'
        );

        $this->app->singleton(ApiRouteRegistrar::class);
    }

    public function boot(): void
    {
        // Publish config for downstream consumers. Migrations are not loaded
        // here: Laravel already scans database/migrations by default, and
        // registering the same directory twice makes the migrator run each
        // migration more than once.
        $this->publishes([
            __DIR__.'/../../config/api_management.php' => config_path('api_management.php'),
        ], 'api-management-config');

        $this->registerDynamicRoutes();
    }

    /**
     * Build a route group for the dynamic API and let the registrar populate it
     * with one entry per active ApiEndpoint row.
     */
    protected function registerDynamicRoutes(): void
    {
        $registrar = $this->app->make(ApiRouteRegistrar::class);

        $prefix = config('api_management.prefix', 'api');
        $rateLimit = (int) config('api_management.rate_limit.attempts', 60);
        $rateDecay = (int) config('api_management.rate_limit.decay_seconds', 60);

        Route::middleware([
            ApiAuthAndAuthorization::class,
            'throttle:'.$rateLimit.','.$rateDecay,
        ])
            ->prefix($prefix)
            ->group(function () use ($registrar) {
                $registrar->register();
            });
    }
}
