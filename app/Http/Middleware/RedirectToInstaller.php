<?php

namespace App\Http\Middleware;

use App\Services\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send every visitor to the installer while the application is unconfigured.
 *
 * ## Why this runs as middleware rather than a route
 *
 * The installer has to be reachable on an application that cannot boot properly:
 * no database, no application key, sessions that cannot start. A route file that
 * calls anything out of the database would not render. This middleware only
 * touches the filesystem, so it works in exactly the state it exists to handle.
 *
 * ## The redirect is narrow on purpose
 *
 * InstallState::needsInstall() is true only when `storage/installed.lock` is
 * absent AND no environment file exists. A database outage does not affect it, so
 * a production site whose MySQL momentarily blips keeps serving (and shows its
 * own error) instead of dumping every visitor onto a page that can create an
 * administrator account.
 *
 * Requiring the absence of `.env` as well is what keeps this safe to ship on a
 * site that already exists. Without that second condition, deploying this to a
 * working installation that predates the marker — or any site that loses the
 * marker — would redirect every request to an installer that then 404s to avoid
 * clobbering the real configuration. That is a total outage caused by the safety
 * feature itself.
 *
 * ## What is not redirected
 *
 *  - The installer's own paths, or this would loop.
 *  - `/up`, Laravel's health endpoint. A load balancer or uptime monitor must be
 *    able to probe an un-installed application without being bounced, or the
 *    instance never reports healthy.
 *  - Nothing here is reachable without one of the exclusions above; the rest of
 *    the application redirects.
 */
class RedirectToInstaller
{
    /**
     * Paths that must answer normally even on an un-installed application.
     *
     * `'up'` is Laravel's registered health route. The others are the installer's
     * own endpoints and its asset route.
     *
     * @var list<string>
     */
    protected array $except = [
        'up',
        'install',
        'install/*',
    ];

    public function __construct(
        private readonly InstallState $state,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if (! $this->state->needsInstall()) {
            return $next($request);
        }

        // Only GET/HEAD are redirected. Redirecting a POST would drop the
        // shopper's form submission on the floor with no indication, and the
        // installer cannot act on it either way.
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        return redirect()->route('install.index');
    }

    /**
     * Whether this request should be answered by the normal application.
     */
    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
