<?php

use App\Exceptions\InvalidOrderStatusException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Store\PageController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApiAuthAndAuthorization;
use App\Http\Middleware\EnsureCustomerHasAddress;
use App\Http\Middleware\EnsureImpersonating;
use App\Http\Middleware\EnsureInstallerIsAvailable;
use App\Http\Middleware\ETagMiddleware;
use App\Http\Middleware\GuestOrCustomerMiddleware;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\TrackAnalytics;
use App\Http\Middleware\UseInstallerSession;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Outside the `web` group: the installer cannot use it, because
            // TrackAnalytics queries the database on every GET and there is no
            // database yet. The group declares its own middleware in the file.
            //
            // Registered BEFORE the /{page} catch-all below, because that route
            // matches any single segment — including "install" — and would
            // otherwise shadow the wizard and send it to PageController, which
            // then queries a pages table that does not exist yet.
            Route::group([], base_path('routes/install.php'));

            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->group(base_path('routes/ajax.php'));

        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'impersonating' => EnsureImpersonating::class,
            'customer.has_address' => EnsureCustomerHasAddress::class,
            'guest_or_customer' => GuestOrCustomerMiddleware::class,
            'api' => ApiAuthAndAuthorization::class,
        ]);

        $middleware->group('installer', [
            EnsureInstallerIsAvailable::class,
            UseInstallerSession::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
        ]);

        // Sends every visitor to the installer while storage/installed.lock is
        // absent. Prepended so it runs before anything that could touch the
        // database or a session — on an un-installed application those would
        // throw, and the redirect would never happen.
        //
        // It does NOT sit in the `web` group: those routes are unreachable while
        // the site is unconfigured, and putting it in the group would mean
        // redirecting a request that had already tried to start a database-backed
        // session.
        $middleware->prependToGroup('web', RedirectToInstaller::class);

        $middleware->prependToGroup('web', TrackAnalytics::class);
        $middleware->prependToGroup('web', SecurityHeadersMiddleware::class);
        $middleware->appendToGroup('web', ETagMiddleware::class);

        $middleware->preventRequestForgery(except: ['stripe/webhook']);

        $middleware->redirectGuestsTo(function () {
            if (request()->is('admin') || request()->is('admin/*')) {
                return route('admin.login');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            return route('home');
        });
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('app:expire-reservations')->hourly();
    })
    ->withCommands([
        // We'll handle production command restrictions via middleware instead
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        /**
         * Branded error pages for every HTTP status (401, 403, 404, 405, 419,
         * 429, 500, 503, and a catch-all).
         *
         * Before resources/views/errors/ existed at all, a bad address hit
         * Laravel's unstyled built-in view — or, with APP_DEBUG=true, the raw
         * Symfony page, which shows a stack trace to whoever mistyped a URL.
         *
         * Scope, deliberately narrow:
         *
         *  - Only HttpExceptionInterface is handled. A ValidationException or
         *    AuthenticationException is not one, so it returns null and Laravel
         *    keeps its own redirect-to-form / redirect-to-login behaviour.
         *  - Non-HTTP Throwables (real crashes) are NOT intercepted, so they fall
         *    through to the framework handler. That is safe only because
         *    APP_DEBUG is false in .env and .env.example: with debug on, Symfony
         *    renders a full trace plus the environment panel, which includes
         *    APP_KEY, DB_PASSWORD and every STRIPE_* value. If you ever turn
         *    APP_DEBUG on locally, remember it leaks the whole .env to whoever
         *    can provoke an error — this app throws plain \Exception from
         *    CartService, InventoryService and the shipping/pricing maths, so a
         *    DivisionByZeroError is enough.
         *  - 404 and 405 render even under debug: a wrong verb or a missing
         *    record is a routing outcome, not a crash, and the trace says
         *    nothing the visitor can act on.
         *
         * The payload is passed explicitly because a registered render callback
         * takes precedence over Laravel's error-view resolver, and that resolver
         * only supplies `$errors`.
         *
         * JSON and /api/* clients get a machine-readable body. Returning null
         * was not enough: once resources/views/errors/ exists, Laravel's own
         * resolver picks these same views up for every 404, so an XHR would
         * otherwise have been handed HTML.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $e instanceof HttpExceptionInterface) {
                return null;
            }

            $status = $e->getStatusCode();
            $headers = $e->getHeaders();
            $wantsJson = $request->expectsJson() || $request->is('api/*');

            if ($wantsJson) {
                $body = [
                    'message' => $status.' '.($e->getMessage() ?: 'Request failed.'),
                    'path' => '/'.$request->path(),
                ];

                // Carry the headers that carry meaning: which verbs a route
                // accepts, and how long a throttled caller must wait.
                if (isset($headers['Allow'])) {
                    $body['allowed'] = $headers['Allow'];
                }
                if (isset($headers['Retry-After'])) {
                    $body['retry_after'] = $headers['Retry-After'];
                }

                return response()->json($body, $status, $headers);
            }

            $data = ['path' => $request->path()];

            if ($status === 405) {
                // Symfony reports the accepted verbs in the Allow header;
                // splitting it lets the page show them as badges.
                $data['verb'] = strtoupper($request->method());
                $data['allowedMethods'] = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) ($headers['Allow'] ?? ''))
                )));
            }

            if ($status === 419) {
                // Expired CSRF token. A reload is the whole recovery, so the
                // page leads with that rather than an apology.
                $data['status'] = $status;
            }

            if ($status === 429 && isset($headers['Retry-After'])) {
                $data['retryAfter'] = (int) $headers['Retry-After'];
            }

            if ($status === 403) {
                // Controller::authorizeAdmin() aborts with a message of the form
                // "You do not have the 'orders.view' permission." — note the
                // quotes come *before* the word. Only that exact shape is lifted
                // into the page, and only a module.action-looking token: an
                // arbitrary 403 message could carry anything, so unrecognised
                // text falls back to the generic copy.
                if (preg_match("/'([a-z0-9_]+(?:\.[a-z0-9_-]+)+)'\s+permission/i", $e->getMessage(), $matches) === 1) {
                    $data['missingPermission'] = $matches[1];
                }
            }

            $view = match ($status) {
                401 => 'errors.401',
                403 => 'errors.403',
                404 => 'errors.404',
                405 => 'errors.405',
                419 => 'errors.419',
                429 => 'errors.429',
                500 => 'errors.500',
                503 => 'errors.503',
                default => 'errors.generic',
            };

            if ($status === 419 || $view === 'errors.generic') {
                $data['status'] = $status;
            }

            return response()->view($view, $data, $status, $headers);
        });

        $exceptions->render(function (InvalidOrderTransitionException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
            }

            return back()->withErrors(['order' => $e->getMessage()]);
        });

        $exceptions->render(function (InvalidOrderStatusException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
            }

            return back()->withErrors(['order' => $e->getMessage()]);
        });
    })->create();
