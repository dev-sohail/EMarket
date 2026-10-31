<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestOrCustomerMiddleware
{
    /**
     * Allow the storefront checkout routes to guests and to signed-in customers.
     *
     * The storefront authenticates against the `web` guard, whose provider is
     * `users` (config/auth.php), so the acting principal is always an
     * App\Models\User — there is no `customer` guard. A shopper is a User row
     * that owns a Customer record, which is what customer self-registration and
     * Admin\CustomerController::createUserAccount() both create. No code path
     * ever assigns a `customer` *role*: RoleSeeder only creates
     * 'Super Admin', 'Admin', 'Editor' and 'Moderator', so gating on
     * User::hasRole('customer') rejected every signed-in shopper with a 403
     * and made checkout impossible for anyone who was logged in.
     *
     * Role membership is therefore used only to recognise back-office staff and
     * steer them to their own dashboard; it never grants or denies a shopper.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Guests may always proceed to checkout/customer-facing routes.
        if (! $user instanceof User) {
            return $next($request);
        }

        // A signed-in shopper owns a Customer record: proceed.
        if ($user->customer !== null) {
            return $next($request);
        }

        // Back-office staff signed into the storefront are sent to their own
        // dashboard instead of silently checking out as an anonymous guest.
        if ($this->hasBackOfficeRole($user)) {
            return redirect()->route('admin.dashboard');
        }

        // Any other authenticated User (for example an account whose Customer
        // record was removed) checks out as a guest would: CheckoutController
        // and CheckoutService both treat a missing customer as a guest order.
        return $next($request);
    }

    /**
     * Determine whether the user holds a back-office role.
     *
     * config('admin.roles') holds the seeded role names; the lowercase
     * 'super_admin'/'admin' entries never match RoleSeeder output but are
     * retained because NotificationService, InventoryController and
     * CustomerController read the same key.
     */
    protected function hasBackOfficeRole(User $user): bool
    {
        return $user->roles()
            ->whereIn('name', config('admin.roles', ['Super Admin', 'Admin']))
            ->exists();
    }
}
