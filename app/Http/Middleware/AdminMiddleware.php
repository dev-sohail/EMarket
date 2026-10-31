<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Gate the back office on a real, active admin-guard session.
     *
     * Three layers, in order:
     *  1. an authenticated principal on the `admin` guard (config/auth.php);
     *  2. an active account that is not currently impersonating a customer;
     *  3. a per-route permission, resolved from the middleware argument
     *     (`->middleware('admin:orders.cancel')`) or from the route name via
     *     config('admin.route_permissions').
     *
     * Layer 3 replaces the old "any user holding any role reaches every
     * screen" rule. The default guard stays "holds at least one role" so no
     * existing route regresses; every route listed in the config map is now
     * additionally checked against User::hasPermission(). Permissions are the
     * `module.action` strings seeded by PermissionSeeder.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @param  string|null  $permission  permission required for this route
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::guard('admin')->user();

        if (! $user->is_active) {
            Auth::guard('admin')->logout();

            return redirect()->route('admin.login')
                ->with('error', 'Your account has been deactivated.');
        }

        // An impersonating session holds both guards at once. The storefront
        // runs as the customer, so the back office must not stay usable until
        // the impersonation is explicitly stopped.
        if (session()->has('impersonator_id') && ! $this->isImpersonationExempt($request)) {
            abort(403, 'Stop the impersonation session before using the back office.');
        }

        // Any user with at least one role still reaches the back office; the
        // permission check below is what narrows individual routes.
        if (! $user->roles()->exists()) {
            return redirect()->route('home')->with('error', 'You do not have admin access.');
        }

        $requiredPermission = $permission ?? $this->permissionForRoute($request);

        if ($requiredPermission !== null && ! $user->hasPermission($requiredPermission)) {
            // Same shape as Controller::authorizeAdmin(): bootstrap/app.php only
            // lifts a quoted `module.action` token onto the branded 403 page.
            abort(403, "You do not have the '{$requiredPermission}' permission.");
        }

        return $next($request);
    }

    /**
     * Resolve the permission a route requires, exact route name first and then
     * by `Str::is` wildcard over the configured route names.
     */
    protected function permissionForRoute(Request $request): ?string
    {
        $routeName = $this->routeName($request);

        if ($routeName === null) {
            return null;
        }

        $map = config('admin.route_permissions', []);

        if (isset($map[$routeName])) {
            return $map[$routeName];
        }

        foreach ($map as $pattern => $requiredPermission) {
            if (Str::is($pattern, $routeName)) {
                return $requiredPermission;
            }
        }

        return null;
    }

    /**
     * Routes that must stay reachable while an impersonation is in progress,
     * otherwise the session could never be unwound.
     */
    protected function isImpersonationExempt(Request $request): bool
    {
        $routeName = $this->routeName($request);

        if ($routeName === null) {
            return false;
        }

        return in_array($routeName, config('admin.impersonation_exempt_routes', []), true);
    }

    protected function routeName(Request $request): ?string
    {
        $route = $request->route();

        if ($route instanceof Route) {
            return $route->getName();
        }

        return null;
    }
}
