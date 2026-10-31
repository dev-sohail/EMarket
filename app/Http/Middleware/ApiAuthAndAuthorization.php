<?php

namespace App\Http\Middleware;

use App\Models\ApiEndpoint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests to dynamically registered API endpoints.
 *
 * Authentication is token-based: a bearer token (or X-API-TOKEN header) is
 * hashed and looked up in the configured tokens table, and the owning user is
 * attached to the request so downstream authorisation can inspect them.
 *
 * Endpoints are not blanket-protected. Each ApiEndpoint row carries its own
 * `requires_auth` flag, and this middleware honours it, so a public read
 * endpoint stays public without needing to be excluded from the route group.
 * A row that cannot be resolved fails closed.
 */
class ApiAuthAndAuthorization
{
    public function handle(Request $request, \Closure $next): Response
    {
        if ($this->endpointRequiresAuth($request) === false) {
            return $next($request);
        }

        $user = $this->authenticate($request);

        if ($user === null) {
            return response()->json([
                'message' => 'Authentication required',
                'error_code' => 'UNAUTHORIZED',
            ], 401);
        }

        // Attach the resolved user so controllers and downstream middleware
        // can rely on $request->user() returning the API principal.
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /**
     * Whether the endpoint behind this route demands authentication.
     *
     * Returns null when the endpoint cannot be resolved, which the caller
     * treats as "authenticate" — an unresolvable endpoint must fail closed.
     *
     * The cache stores a boolean, not the model. Caching the Eloquent object
     * made this unserialise into `__PHP_Incomplete_Class` whenever the entry
     * was read back in a process where the model class was not yet
     * autoloadable, and the resulting TypeError turned every protected request
     * into a 500 instead of the intended 401.
     */
    protected function endpointRequiresAuth(Request $request): ?bool
    {
        $endpointId = $this->endpointIdFromRoute($request);

        if ($endpointId === null) {
            return null;
        }

        return Cache::remember(
            'api.endpoint.requires_auth.'.$endpointId,
            60,
            fn () => ApiEndpoint::find($endpointId)?->requires_auth
        );
    }

    /**
     * Recover the endpoint id from the matched route's name.
     *
     * The registrar names every route `api.dynamic.{endpointId}`, so the id is
     * recoverable from the route name without re-parsing the path.
     */
    protected function endpointIdFromRoute(Request $request): ?string
    {
        $route = $request->route();

        if ($route === null || $route->getName() === null) {
            return null;
        }

        $prefix = 'api.dynamic.';

        if (! str_starts_with($route->getName(), $prefix)) {
            return null;
        }

        $endpointId = substr($route->getName(), strlen($prefix));

        return $endpointId === '' ? null : $endpointId;
    }

    /**
     * Resolve the API principal from the bearer token, or null when the
     * request is unauthenticated.
     */
    protected function authenticate(Request $request): ?User
    {
        $token = $request->bearerToken() ?? $request->header('X-API-TOKEN');

        if (empty($token)) {
            return null;
        }

        // The file is config/api_management.php, so the key is `api_management`.
        // The hyphenated spelling this used to read is only populated by the
        // provider's mergeConfigFrom(), which Laravel skips entirely once the
        // config is cached, so the override silently stopped working in
        // production.
        $table = config('api_management.tokens_table', 'api_tokens');

        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return null;
        }

        $record = DB::table($table)
            ->where('token', hash('sha256', $token))
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($record === null) {
            return null;
        }

        if (empty($record->user_id)) {
            return null;
        }

        // A deactivated account must not keep API access through a token that
        // was issued while it was active. SoftDeletes already hides deleted users.
        return User::where('_id', $record->user_id)
            ->where('is_active', true)
            ->first();
    }
}
