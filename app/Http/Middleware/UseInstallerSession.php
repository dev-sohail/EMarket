<?php

namespace App\Http\Middleware;

use App\Services\Install\TemporaryApplicationKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces the file-based session driver for the installer's own requests.
 *
 * The application's `web` group cannot be used by the installer. Its session
 * driver is `database` (config/session.php defaults to it when `SESSION_DRIVER`
 * is unset), and on an un-installed application there is no database — so
 * starting a session, and therefore rendering the CSRF token the installer's
 * form needs, would fail on the one page that exists to fix exactly that.
 *
 * This middleware must run before StartSession, and it does: it is listed ahead
 * of the session middleware in the `installer` group.
 *
 * Setting `config(['session.driver' => 'file'])` works because the session
 * manager reads its driver when it is first resolved, which happens inside
 * StartSession — after this has already run.
 */
class UseInstallerSession
{
    public function __construct(
        private readonly TemporaryApplicationKey $temporaryKey,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (($key = $this->temporaryKey->resolve()) !== null) {
            config(['app.key' => $key]);
        }

        config([
            'session.driver' => 'file',
            // The file driver also needs somewhere to write. If storage is not
            // writable the installer is already unusable and the failure is
            // better reported by the requirements check than as an opaque
            // session error here.
            'cache.default' => 'file',
        ]);

        return $next($request);
    }
}
