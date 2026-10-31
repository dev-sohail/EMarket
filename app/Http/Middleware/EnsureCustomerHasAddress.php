<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerHasAddress
{
    /**
     * Require the acting customer to own at least one delivery address.
     *
     * Addresses hang off Customer (Customer::addresses()), not off the
     * authenticated User: User only defines roles() and customer(). The former
     * $user->addresses()->count() call raised BadMethodCallException for every
     * request, which made the whole customer order/return area unreachable.
     *
     * Querying through $user->customer->addresses() also means the address is
     * by construction the one belonging to the acting customer, so an address
     * id belonging to somebody else can never satisfy this check.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The route group applies this middleware behind 'auth', so a missing
        // principal is unreachable in practice; refuse rather than pass.
        if (! $user instanceof User) {
            abort(403, 'Authentication is required to view this area.');
        }

        $customer = $user->customer;

        if ($customer === null) {
            abort(403, 'This area is only available to registered customers.');
        }

        if ($customer->addresses()->exists()) {
            return $next($request);
        }

        // Never redirect onto the address form itself: that would loop. These
        // routes are not inside this middleware's group today, so this only
        // matters if the group is ever widened.
        if ($request->routeIs('customer.addresses.create', 'customer.addresses.store')) {
            return $next($request);
        }

        // A POST/PUT/DELETE must not be answered with a redirect: the browser
        // would replay it as a GET and the submitted payload (return reason,
        // order reference) would be lost. Deny it explicitly instead.
        if (! $request->isMethodSafe()) {
            abort(403, 'Add a delivery address before submitting this form.');
        }

        return redirect()->guest(route('customer.addresses.create'))
            ->with('error', 'Please add at least one address before proceeding.');
    }
}
