<?php

namespace App\Http\Middleware;

use App\Services\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Close the installer once the application is installed.
 *
 * This is the other half of RedirectToInstaller. That middleware only sends
 * traffic to the installer while the marker is absent; this one makes the
 * installer itself answer 404 the moment the marker exists, so the
 * write-capable endpoints are not merely unlinked but unreachable.
 *
 * Two independent conditions are checked rather than one, because either alone
 * leaves a gap:
 *
 *  - The marker file. This is the real lock.
 *  - The environment file. If `.env` exists but the marker does not, something
 *    deleted the marker on a working site — a backup restore, a partial deploy, a
 *    clean checkout. That is not a fresh install and must not be treated as one,
 *    so the installer refuses regardless of the marker.
 *
 * Aborting with 404 rather than a redirect or a 403: a redirect invites
 * enumeration of whether the installer exists, and 403 confirms it does.
 */
class EnsureInstallerIsAvailable
{
    public function __construct(
        private readonly InstallState $state,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->state->locked(), 404);

        abort_if($this->state->environmentFileExists(), 404);

        return $next($request);
    }
}
