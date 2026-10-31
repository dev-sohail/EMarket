<?php

namespace App\Http\Controllers;

use App\Services\Install\EnvFileWriter;
use App\Services\Install\InstallerService;
use App\Services\Install\InstallState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/**
 * The installation wizard.
 *
 * Reachable only while `storage/installed.lock` is absent — see
 * EnsureInstallerIsAvailable, which 404s every route here the moment it exists.
 *
 * Two properties of this controller matter more than the form handling:
 *
 *  1. It renders on an application that cannot boot properly. No database, no
 *     application key, no usable sessions. Every collaborator is therefore
 *     filesystem- or PDO-based, and the routes use the `installer` middleware
 *     group rather than `web`.
 *
 *  2. It is write-capable and, by definition, unauthenticated — nobody has an
 *     account yet. So it refuses to run against anything that looks configured,
 *     reports no driver message on a connection failure, and marks the
 *     application installed only after every step has succeeded.
 */
class InstallController extends Controller
{
    public function __construct(
        private readonly InstallState $state,
        private readonly InstallerService $installer,
        private readonly EnvFileWriter $envWriter,
    ) {}

    /**
     * Show the wizard.
     */
    public function index(): View|RedirectResponse
    {
        // Already installed. The middleware should have 404'd this; belt and
        // braces, because reaching the installer with a populated database would
        // be the worst possible outcome.
        if ($this->state->locked()) {
            abort(404);
        }

        // A `.env` without a marker is almost certainly a live site that lost
        // its marker. Say so plainly instead of offering to overwrite it.
        if ($this->state->environmentFileExists()) {
            return view('install.already-configured');
        }

        return view('install.index', [
            'requirements' => $this->requirements(),
        ]);
    }

    /**
     * Verify a set of database credentials without writing anything.
     */
    public function testConnection(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            $this->envWriter->rules(),
            $this->envWriter->messages(),
        );

        $result = $this->installer->testConnection($validated);

        return back()
            ->with('connection_test', $result)
            ->withInput($request->except(['db_password']));
    }

    /**
     * Run the installation.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($this->state->locked()) {
            abort(404);
        }

        $validated = $request->validate(
            $this->envWriter->rules(),
            $this->envWriter->messages(),
        );

        $progress = [];

        try {
            $this->installer->install(
                $validated,
                function (string $line) use (&$progress): void {
                    $progress[] = $line;
                }
            );
        } catch (Throwable $e) {
            // Already sanitised by InstallerService: fixed copy plus at most one
            // truncated line, never the driver's own message. report() carries the
            // full exception for the operator's log.
            report($e);

            // Provide a more helpful user-facing message for the most common
            // failure modes, while never leaking driver internals.
            //
            // Matching is case-insensitive: InstallerService throws "Seeding X
            // failed: <driver line>" with a capital S, and the old case-sensitive
            // 'seeding' test let that driver text (SQL, table names) through to
            // the page. Anything that is not one of the known InstallerService
            // messages falls back to fixed copy instead of being echoed.
            $message = strtolower($e->getMessage());

            if (str_contains($message, 'the migrations failed')) {
                $message = 'The database migrations could not run. Check that the database exists and that the credentials can access it, then review the log for details.';
            } elseif (str_contains($message, 'seeding')) {
                $message = 'The initial data could not be loaded. Review the log for details.';
            } elseif (str_contains($message, 'super admin role was not seeded')) {
                $message = 'The administrator account could not be created because the required roles were not set up. Review the log for details.';
            } elseif (str_contains($message, 'administrator account could not be created')) {
                $message = 'The administrator account could not be created. Review the log for details.';
            } elseif ($e instanceof \InvalidArgumentException
                || str_starts_with($message, 'could not create or reach the database')
                || str_starts_with($message, 'this application already has an environment file')
                || str_starts_with($message, 'refusing to overwrite an existing .env')) {
                // Fixed, deliberate copy written by InstallerService/EnvFileWriter.
                $message = $e->getMessage();
            } else {
                $message = 'The installation could not be completed. Review the log for details.';
            }

            return back()
                ->withInput($request->except(['db_password', 'admin_password', 'admin_password_confirmation']))
                ->withErrors(['install' => $message])
                ->with('progress', $progress);
        }

        // The marker now exists, so RedirectToInstaller will stop bouncing
        // traffic here on the next request. Clearing the route cache matters for
        // the dynamic API routes, which were skipped at boot because the
        // api_endpoints table did not exist when this request started.
        $this->clearCaches();

        return redirect()->route('install.complete');
    }

    /**
     * Shown once, immediately after a successful install.
     *
     * Registered outside the `installer` group so it remains reachable the
     * moment the marker exists; it redirects to the storefront login with a
     * success note rather than serving a page, so the operator does not get
     * lost on a locked-off route.
     */
    public function complete(): RedirectResponse
    {
        // A fresh installation has no admin session, so there is nothing to log
        // into yet. Send them to the storefront login with a note.
        return redirect()->route('home')
            ->with('success', 'Installation complete. Sign in to the admin area with the account you just created.');
    }

    /**
     * Checks the installer itself depends on.
     *
     * Reported rather than enforced: an operator on shared hosting should be
     * told what is missing instead of being handed a fatal error halfway
     * through. The ones that would truly stop an install are re-checked in
     * InstallerService, which throws.
     *
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    protected function requirements(): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $checks[] = [
            'label' => 'PHP 8.2 or newer',
            'ok' => $phpOk,
            'detail' => 'Running '.PHP_VERSION,
        ];

        foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'fileinfo', 'json'] as $extension) {
            $checks[] = [
                'label' => "PHP extension: {$extension}",
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'Loaded' : 'Missing — ask your host to enable it',
            ];
        }

        $checks[] = [
            'label' => 'Writable: storage/',
            'ok' => is_writable(storage_path()),
            'detail' => is_writable(storage_path())
                ? storage_path()
                : storage_path().' is not writable; the installation marker and session files need it',
        ];

        $checks[] = [
            'label' => 'Writable: bootstrap/cache/',
            'ok' => is_writable(base_path('bootstrap/cache')),
            'detail' => is_writable(base_path('bootstrap/cache'))
                ? base_path('bootstrap/cache')
                : base_path('bootstrap/cache').' is not writable; Laravel writes its compiled files here',
        ];

        $checks[] = [
            'label' => 'Configuration file not yet present',
            'ok' => ! $this->state->environmentFileExists(),
            'detail' => '.env is written during installation',
        ];

        return $checks;
    }

    /**
     * Drop caches that were built against the pre-install world.
     *
     * The config cache in particular: if it exists from a previous deployment it
     * holds the old (or default) database connection and would override the .env
     * just written. Silently ignored if it cannot be cleared — the installer has
     * already succeeded by this point and an operator can run it by hand.
     */
    protected function clearCaches(): void
    {
        foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable $e) {
                report(new \RuntimeException("Failed to clear caches: {$command} failed", 0, $e));
            }
        }
    }
}
