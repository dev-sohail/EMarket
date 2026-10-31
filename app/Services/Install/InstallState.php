<?php

namespace App\Services\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Answers one question: is this application installed?
 *
 * "Installed" is defined by a marker file the installer writes as its very last
 * step — `storage/installed.lock`. Nothing else in the codebase decides this, so
 * there is a single fact to reason about.
 *
 * ## Why a marker file and not "does the database have tables?"
 *
 * A table-existence check would be a takeover vector. On a live site the
 * database is reachable, so the check answers "installed" and nothing happens —
 * but the moment the database is briefly unreachable (a restart, a network blip,
 * a connection limit) every visitor is redirected to an installer that writes a
 * fresh `.env`, runs migrations and creates an admin account with an
 * attacker-chosen password. The database being *down* must never be able to
 * unlock a write-capable endpoint.
 *
 * A file on local disk does not fail that way: it is either there or it is not,
 * and it does not become readable-or-not based on a service being up.
 *
 * ## Failing closed
 *
 * Anything ambiguous resolves to *installed*. `locked()` swallows filesystem
 * errors and returns true on failure, so an unreadable or permission-broken
 * storage directory disables the installer rather than exposing it. The only
 * state that opens the installer is the explicit, unambiguous "marker absent".
 *
 * ## Deleting the marker does not re-open it silently
 *
 * Deleting `storage/installed.lock` on a live server would re-open the
 * installer, so the installer additionally refuses to run when it finds an
 * existing, populated `.env`. That is enforced in InstallerService, not here —
 * this class only reports state.
 */
class InstallState
{
    /**
     * Relative path of the marker, under storage_path().
     */
    public const LOCK_RELATIVE_PATH = 'installed.lock';

    /**
     * Key under which the marker records its own state, so a truncated or
     * hand-written file that happens to exist is not mistaken for a real one.
     */
    private const LOCK_KEY = 'poweredin_installed';

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * Absolute path of the marker file.
     */
    public function lockPath(): string
    {
        return storage_path(self::LOCK_RELATIVE_PATH);
    }

    /**
     * Whether the application has been installed.
     *
     * Fails closed: any error resolving the answer is reported as installed.
     */
    public function locked(): bool
    {
        try {
            return $this->files->exists($this->lockPath());
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Whether the installer should be offered.
     *
     * Requires BOTH the marker to be absent AND no environment file to exist.
     *
     * The second condition is not redundant with the first, and it is the one
     * that matters on an existing deployment. A site that was set up before this
     * feature existed — or one whose marker was lost — has a working `.env` and
     * a populated database but no marker. Redirecting on the marker alone would
     * point every visitor at the installer, while the installer itself
     * correctly 404s (refusing to overwrite a real configuration). The result is
     * a site that redirects every request to a 404: broken, with no way in.
     *
     * So "needs install" means "nothing has been configured yet", and an
     * existing environment file is positive evidence to the contrary. Only a
     * genuinely bare checkout has neither.
     */
    public function needsInstall(): bool
    {
        if ($this->locked()) {
            return false;
        }

        return ! $this->environmentFileExists();
    }

    /**
     * Write the marker. Called once, after migrations and seeding have
     * succeeded, so a half-finished install never leaves a site that believes
     * it is configured.
     */
    public function markInstalled(): void
    {
        $path = $this->lockPath();

        $this->files->ensureDirectoryExists(dirname($path));

        // Write to a temporary name and rename, so a concurrent request never
        // observes a partially written file and mistakes it for the real thing.
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        $this->files->put($temporary, json_encode([
            self::LOCK_KEY => true,
            'installed_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->files->move($temporary, $path);

        @chmod($path, 0644);
    }

    /**
     * Remove the marker. Only ever called from a deliberate, authenticated
     * reinstall — never from a request path.
     */
    public function clear(): void
    {
        if ($this->files->exists($this->lockPath())) {
            $this->files->delete($this->lockPath());
        }
    }

    /**
     * Whether an environment file is already present.
     *
     * The installer's second line of defence: if a `.env` already exists, this
     * is very likely a live installation whose marker was lost, and overwriting
     * the operator's database credentials would break a working site.
     */
    public function environmentFileExists(): bool
    {
        return $this->files->exists($this->environmentFilePath());
    }

    public function environmentFilePath(): string
    {
        return app()->environmentFilePath();
    }

    /**
     * Read a single key out of raw dotenv text.
     *
     * Deliberately does not use Dotenv: this runs before the framework is
     * configured, and a malformed file must not throw here. Returns null when
     * the key is absent or its line is malformed.
     */
    protected function readEnvValue(string $contents, string $key): ?string
    {
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_starts_with($line, $key.'=')) {
                continue;
            }

            $value = trim(substr($line, strlen($key) + 1));

            // Strip one layer of matching quotes.
            if (strlen($value) >= 2
                && ($value[0] === '"' || $value[0] === "'")
                && $value[strlen($value) - 1] === $value[0]
            ) {
                $value = substr($value, 1, -1);
            }

            return $value;
        }

        return null;
    }
}
