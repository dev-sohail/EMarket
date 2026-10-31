<?php

namespace App\Services\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Supplies a temporary application key while nothing is configured.
 *
 * ## Why this exists
 *
 * With no `.env` there is no `APP_KEY`, and the framework cannot complete a
 * request even to render an error page: `terminateMiddleware()` re-resolves
 * every middleware after the response has been sent, `StartSession` resolves
 * the session manager, which resolves the encrypter, which throws
 * MissingAppKeyException. Headers are already sent by then, so the visitor gets
 * a fatal "Cannot modify header information" and a 500 — never the installer.
 *
 * That makes a placeholder key a prerequisite for the installer existing at all,
 * not a convenience.
 *
 * ## Why it is safe
 *
 *  - It only ever runs when `APP_KEY` resolves to nothing. An installed
 *    application always has one, so the real key is used and this code path is
 *    never reached.
 *  - The value is random per installation and is never written to `.env`; the
 *    installer generates the real key with `random_bytes(32)` when it writes one.
 *  - It exists only for the duration of an install. After a successful install
 *    the real key takes over and the temporary file is deleted by
 *    InstallerService.
 *
 * ## Why the key is persisted
 *
 * It has to be stable across requests, not random per request. The install form
 * relies on CSRF: the token is written into an encrypted cookie on the GET and
 * verified on the POST. A per-request key would make every submission fail with
 * a 419, so the wizard could never be completed.
 */
class TemporaryApplicationKey
{
    /**
     * Relative path, under storage_path(). Not the same directory as
     * installed.lock: this file is an implementation detail of booting, and the
     * marker is the installer's own state.
     */
    public const RELATIVE_PATH = 'framework/bootstrap-temporary-key';

    /**
     * Lock file to prevent race conditions during key generation.
     */
    public const LOCK_RELATIVE_PATH = 'framework/bootstrap-temporary-key.lock';

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * The temporary key path.
     */
    public function path(): string
    {
        return storage_path(self::RELATIVE_PATH);
    }

    /**
     * The lock file path.
     */
    public function lockPath(): string
    {
        return storage_path(self::LOCK_RELATIVE_PATH);
    }

    /**
     * Resolve a key to use, or null when the application has a real one.
     *
     * Called before the framework is configured, so it reads the environment the
     * same way bootstrap/app.php does and never assumes config() is available.
     */
    public function resolve(): ?string
    {
        // A real key always wins. This is the single most important condition in
        // the class: nothing here may ever override an installed application's
        // encryption key.
        if ($this->configuredKey() !== null) {
            return null;
        }

        return $this->storedKey() ?? $this->generateWithLock();
    }

    /**
     * Delete the stored temporary key. Called after a successful install, when
     * the real key has taken over.
     */
    public function forget(): void
    {
        if ($this->files->exists($this->path())) {
            $this->files->delete($this->path());
        }

        // Also clean up the lock file
        if ($this->files->exists($this->lockPath())) {
            $this->files->delete($this->lockPath());
        }
    }

    /**
     * The key already present in the environment, if any.
     */
    protected function configuredKey(): ?string
    {
        // Read via getenv/$_ENV/$_SERVER rather than env(): this runs during
        // bootstrap, and env() may not be reading what the framework will read
        // yet. All three are checked so the answer does not depend on which
        // adapter happens to be active.
        $value = $_SERVER['APP_KEY'] ?? $_ENV['APP_KEY'] ?? getenv('APP_KEY');

        if (! is_string($value)) {
            $value = $_SERVER['APP_KEY'] ?? null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * The previously generated temporary key, if one was written.
     */
    protected function storedKey(): ?string
    {
        $path = $this->path();

        try {
            if (! $this->files->exists($path)) {
                return null;
            }

            $stored = trim($this->files->get($path));

            return $stored === '' ? null : $stored;
        } catch (\Throwable) {
            // Unreadable: fall through and generate a fresh one rather than
            // letting a filesystem problem abort the boot entirely.
            return null;
        }
    }

    /**
     * Generate and persist a fresh temporary key with lock protection.
     */
    protected function generateWithLock(): string
    {
        $lockPath = $this->lockPath();
        $this->files->ensureDirectoryExists(dirname($lockPath));

        // Open lock file for reading and writing, create if it doesn't exist
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            // If we can't create the lock file, fall back to non-locked version
            return $this->generate();
        }

        try {
            // Attempt to acquire an exclusive lock
            if (! flock($handle, LOCK_EX)) {
                // If we can't get the lock, fall back to non-locked version
                return $this->generate();
            }

            // Double-check: another process may have created the key while we waited for the lock
            $stored = $this->storedKey();
            if ($stored !== null) {
                return $stored;
            }

            // Generate and store the key
            return $this->generate();
        } finally {
            // Release the lock and close the file handle
            flock($handle, LOCK_UN);
            fclose($handle);
            // Clean up the lock file
            @unlink($lockPath);
        }
    }

    /**
     * Generate and persist a fresh temporary key.
     */
    protected function generate(): string
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        try {
            $this->files->ensureDirectoryExists(dirname($this->path()));
            $this->files->put($this->path(), $key);

            // It encrypts cookies for the duration of the install. Group-readable
            // rather than 0600, because the web server is not the owner of this
            // checkout — see EnvFileWriter::applyRestrictivePermissions().
            @chmod($this->path(), 0660);
        } catch (\Throwable) {
            // A read-only storage directory still lets this request finish; only
            // a multi-step install would then fail its CSRF check.
        }

        return $key;
    }
}
