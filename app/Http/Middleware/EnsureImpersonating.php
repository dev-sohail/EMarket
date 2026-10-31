<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureImpersonating
{
    /**
     * Require a *currently valid* impersonation, not just a session flag.
     *
     * session('impersonator_id') is written once by
     * Admin\CustomerController::impersonate() and was then trusted verbatim on
     * every later request, so the flag survived the impersonator being deleted,
     * deactivated or stripped of their back-office role, and nothing checked
     * that the web guard was still acting as somebody other than the admin.
     * That is the escape hatch a nested impersonation would use.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $impersonatorId = session('impersonator_id');

        if (! is_string($impersonatorId) || $impersonatorId === '') {
            abort(403, 'No impersonation session is active.');
        }

        $impersonator = User::find($impersonatorId);

        if (! $this->isValidImpersonation($impersonator, $request->user())) {
            return $this->terminate($request, 'Your impersonation session is no longer valid. Please sign in again.');
        }

        // While impersonating, the back office must not stay reachable through
        // the admin guard. AdminMiddleware already refuses every admin route
        // except the stop-impersonation route; dropping the guard here closes
        // the same hole for any route that carries only this alias.
        // CustomerController::stopImpersonating() logs the admin back in.
        Auth::guard('admin')->logout();

        return $next($request);
    }

    /**
     * Re-validate the impersonation against live state on every request.
     *
     * @param  User|null  $impersonator  the account recorded in the session
     * @param  User|null  $actingUser  the principal on the web guard
     */
    protected function isValidImpersonation(?User $impersonator, ?User $actingUser): bool
    {
        if (! $impersonator instanceof User || ! $actingUser instanceof User) {
            return false;
        }

        if (! $impersonator->is_active) {
            return false;
        }

        // The recorded admin must still be a back-office user, otherwise the
        // flag outlived the privilege that authorised the impersonation.
        $isBackOffice = $impersonator->roles()
            ->whereIn('name', config('admin.roles', ['Super Admin', 'Admin']))
            ->exists();

        if (! $isBackOffice) {
            return false;
        }

        // Self-impersonation: the web guard is still the admin, so there is
        // nothing to stop and the session would be a back-office session
        // wearing an impersonation flag.
        return $actingUser->getKey() !== $impersonator->getKey();
    }

    /**
     * Tear a stale impersonation down instead of leaving a half-state session.
     */
    protected function terminate(Request $request, string $message): RedirectResponse
    {
        session()->forget('impersonator_id');

        Auth::guard('web')->logout();
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('error', $message);
    }
}
