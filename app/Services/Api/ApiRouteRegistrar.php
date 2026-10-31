<?php

namespace App\Services\Api;

use App\Http\Controllers\Api\ApiDynamicController;
use App\Models\ApiEndpoint;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Registers every routable ApiEndpoint row as a real Laravel route.
 *
 * Routability is decided by the `routable` scope on ApiEndpoint, which the
 * reflection pipeline's conflict check also uses — one definition of "should
 * this endpoint be published", not two that can drift apart.
 *
 * Registration happens once, at application boot, by the
 * ApiManagementServiceProvider. After that the routes behave like any other
 * Laravel route: they appear in `route:list`, they are matched by the router
 * and they are cached by `route:cache` / `route:clear`.
 *
 * The registrar is deliberately stateless with respect to the request: it
 * reads the database once and hands the Router the closures it needs. The
 * actual dispatch (parameter binding, validation, handler invocation) is the
 * job of ApiDynamicController, so this class only knows how to turn a row
 * into a route definition.
 */
class ApiRouteRegistrar
{
    /**
     * Register every routable endpoint as a Laravel route.
     *
     * Inactive endpoints are skipped, so toggling `is_active` on a row is
     * enough to take an endpoint live or retire it without touching code.
     *
     * Gracefully handles missing tables (e.g. during migrations) so that
     * `php artisan migrate` does not fail.
     */
    public function register(): void
    {
        if (! Schema::hasTable('api_endpoints')) {
            return;
        }

        if (! $this->tablesExist()) {
            return;
        }

        try {
            ApiEndpoint::routable()
                ->with('parameters')
                ->orderBy('sort_order')
                ->get()
                ->each(function (ApiEndpoint $endpoint) {
                    $this->registerEndpoint($endpoint);
                });
        } catch (QueryException) {
            // If the tables don't exist yet (fresh install or migration),
            // silently skip route registration.
        }
    }

    /**
     * Register a single endpoint, swallowing exceptions so one bad row can
     * never prevent the rest of the route set from loading.
     */
    protected function registerEndpoint(ApiEndpoint $endpoint): void
    {
        try {
            $uri = $this->buildUri($endpoint->path);

            Route::addRoute(
                strtoupper($endpoint->method),
                $uri,
                ApiDynamicController::class
            )
                ->name('api.dynamic.'.$endpoint->_id)
                // Implicit model binding must stay off these routes. A stored
                // path segment like `{product}` matches Laravel's `{model}`
                // convention, so SubstituteBindings would swap the raw
                // identifier for a full model instance before the controller
                // ever runs. That silently gives handlers the wrong value (an
                // object where they declared a string), applies the default
                // model's scoping rules instead of the endpoint's own, and
                // turns a missing row into a framework 404 before the handler
                // can answer with its own error shape.
                ->withoutMiddleware(SubstituteBindings::class);
        } catch (\Throwable) {
            // A malformed path or duplicate registration must not break boot.
            // The endpoint stays in the database and is surfaced by
            // `api:validate` so an admin can fix it.
        }
    }

    /**
     * Check that the API management tables exist before querying.
     *
     * This prevents failures during `php artisan migrate` on a fresh database.
     *
     * The whole check is wrapped because `hasTable()` does not merely return
     * false for an absent database — it throws. With no `.env` at all the default
     * connection is sqlite pointing at a file that does not exist, and the
     * PDOException escapes. That killed application boot outright, which is
     * exactly the state the installer has to render in: the installer needs the
     * app to boot far enough to show its "configure the database" step. Swallowing
     * it here means a missing database costs us the dynamic API routes and
     * nothing else.
     */
    protected function tablesExist(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('api_endpoints') && DB::getSchemaBuilder()->hasTable('api_definitions');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Build the route URI from the stored path.
     *
     * The configured prefix is applied by the route group the provider opens,
     * so the stored path is used verbatim here. `{param}` segments are left
     * as-is: Laravel parses them into route parameters automatically, and the
     * controller re-validates each against the declared parameter rules.
     */
    protected function buildUri(string $path): string
    {
        return trim($path, '/');
    }
}
