<?php

namespace App\Services\Install;

use App\Models\Role;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the actual installation: create the database, write `.env`, migrate, seed.
 *
 * Everything here is reachable over HTTP on an un-installed application, so it is
 * treated as hostile input throughout. The guards that matter:
 *
 *  - `assertFreshInstall()` refuses to run when an environment file already
 *    exists. Without it, losing `storage/installed.lock` on a live server would
 *    be enough for a visitor to overwrite the operator's database credentials
 *    and create an admin account.
 *  - `assertTargetIsEmpty()` refuses to run against a database that already
 *    holds data, for the same reason from the other direction.
 *  - Nothing is echoed back. Connection failures are reported as
 *    "could not connect", not with the driver's message, which carries the host,
 *    the username and sometimes the rejected password.
 */
class InstallerService
{
    public function __construct(
        private readonly InstallState $state,
        private readonly EnvFileWriter $envWriter,
        private readonly Filesystem $files,
        private readonly TemporaryApplicationKey $temporaryKey,
    ) {}

    /**
     * Whether an environment file already exists, which means this is very
     * likely a live site that lost its marker rather than a fresh deployment.
     */
    public function looksConfigured(): bool
    {
        return $this->state->environmentFileExists();
    }

    /**
     * Probe a database connection without persisting anything.
     *
     * Used by the wizard's "test connection" step. The error is reduced to a
     * fixed string; the real message goes to the log.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(array $settings): array
    {
        // assertSettingsUsable() runs outside the try so a malformed setting
        // surfaces as an exception rather than being reported as "could not
        // connect". The two mean different things to the operator: a connection
        // failure says check the credentials, a rejected setting says the input
        // never got as far as a query. The form's pattern rules normally catch
        // these first, so reaching here means a caller bypassed validation — and
        // silently converting that into a connection error would hide a
        // malformed value the moment it starts mattering.
        $this->assertSettingsUsable($settings);

        try {
            $pdo = $this->makePdo($settings, false);

            // Confirms the server answers, without creating anything.
            $pdo->query('SELECT 1');

            return ['ok' => true, 'message' => 'Connected successfully.'];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'Could not connect with those details. Check the host, port, database name and credentials.'];
        }
    }

    /**
     * Perform the installation.
     *
     * @param  array<string, string>  $settings
     * @param  callable(string): void|null  $output  Receives progress lines for the UI.
     *
     * @throws \RuntimeException on any failure, with a message safe to display.
     */
    public function install(array $settings, ?callable $output = null): void
    {
        $this->assertFreshInstall();
        $this->assertSettingsUsable($settings);

        $log = $output ?? static function (string $line): void {};

        // 1. Create the database if it does not exist. Connecting without a
        //    database is the only way to issue CREATE DATABASE.
        $log('Connecting to the database server…');

        try {
            $server = $this->makePdo($settings, false);
            $name = $settings['db_database'];

            // The name has already passed EnvFileWriter's pattern rules, so it is
            // a bare identifier and cannot carry SQL. It is still quoted, because
            // "cannot carry SQL" is a property of the allow-list and quoting is
            // free.
            $server->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '', $name).'` '
                .'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable $e) {
            report($e);

            throw new \RuntimeException(
                'Could not create or reach the database. Check that the MySQL server is running and that the '
                .'credentials are allowed to create databases, then try again.'
            );
        }

        // 2. Write .env. Done before migrating because migrations need these
        //    credentials, and the config is read from the file.
        $log('Writing the environment file…');

        $appKey = $this->generateApplicationKey();

        $this->envWriter->write($this->envWriter->render([
            'app_name' => $settings['app_name'],
            'app_key' => $appKey,
            'app_url' => $settings['app_url'],
            'db_host' => $settings['db_host'],
            'db_port' => (string) $settings['db_port'],
            'db_database' => $settings['db_database'],
            'db_username' => $settings['db_username'],
            'db_password' => (string) ($settings['db_password'] ?? ''),
        ]));

        // From here on a failure must not strand the site. The .env just
        // written makes InstallState::needsInstall() false and the installer
        // routes 404, so leaving it behind after a failed migration would lock
        // the operator out of the wizard with a half-installed database and no
        // marker. The file did not exist a moment ago (assertFreshInstall and
        // EnvFileWriter::write both refuse to overwrite one), so removing it
        // restores exactly the state the install started from.
        try {
            $this->provision($settings, $log);
        } catch (Throwable $e) {
            $this->discardEnvironmentFile();

            throw $e;
        }

        // The real key is now in .env. Drop the placeholder so the next request
        // boots with the real one; leaving it behind would mean a stale key
        // persists until the file is deleted by hand.
        $this->temporaryKey->forget();

        $log('Installation complete.');
    }

    /**
     * Steps 3-6 of the installation: everything that needs the new .env.
     *
     * @param  callable(string): void  $log
     */
    protected function provision(array $settings, callable $log): void
    {
        $this->reloadConfiguration();

        // 3. Migrations.
        $log('Creating the database tables…');

        try {
            $exit = Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            report($e);

            throw new \RuntimeException('The migrations failed: '.$this->firstLine($e->getMessage()));
        }

        if ($exit !== 0) {
            throw new \RuntimeException('The migrations failed. Check the log for details.');
        }

        // 4. Seed. Roles, permissions, settings and the API definitions the
        //    storefront depends on. AdminSeeder is deliberately excluded — the
        //    administrator is created below from the operator's chosen password,
        //    and that password is never written to .env.
        $log('Seeding roles, permissions and settings…');

        foreach (['RoleSeeder', 'PermissionSeeder', 'RolePermissionSeeder', 'SettingSeeder', 'ApiManagementSeeder', 'PaymentMethodSeeder', 'PageSeeder'] as $seeder) {
            try {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
            } catch (Throwable $e) {
                report($e);

                throw new \RuntimeException("Seeding {$seeder} failed: ".$this->firstLine($e->getMessage()));
            }
        }

        // 5. The administrator.
        $log('Creating the administrator account…');

        $this->createAdministrator($settings);

        // 6. Only now is the application configured. Written last, and written
        //    atomically by InstallState, so an install that fails at any earlier
        //    step leaves the site still offering the installer.
        $log('Finalising…');

        $this->state->markInstalled();
    }

    /**
     * Remove the .env this installer wrote after a failed install, so the
     * wizard is offered again. Never throws: the original failure is what the
     * caller needs to see.
     */
    protected function discardEnvironmentFile(): void
    {
        try {
            // Never delete once the marker exists: that is a finished install.
            if (! $this->state->locked()) {
                $this->files->delete($this->state->environmentFilePath());
            }

            DB::purge();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Refuse to install over what looks like a working site.
     *
     * @throws \RuntimeException
     */
    protected function assertFreshInstall(): void
    {
        if ($this->state->environmentFileExists()) {
            throw new \RuntimeException(
                'This application already has an environment file, so it looks installed. Delete .env and '
                .'storage/installed.lock by hand if you are certain you want to reinstall — the installer will '
                .'not overwrite them for you.'
            );
        }
    }

    /**
     * Build a PDO handle for the submitted settings.
     *
     * `withDatabase = false` connects to the server itself, which is what
     * CREATE DATABASE needs. Credentials are never logged; report() carries the
     * exception but the message shown to the operator is fixed copy.
     *
     * @throws \InvalidArgumentException when the settings are unusable
     * @throws \RuntimeException when the connection cannot be made
     */
    protected function makePdo(array $settings, bool $withDatabase): \PDO
    {
        $this->assertSettingsUsable($settings);

        $dsn = sprintf(
            'mysql:host=%s;port=%d%s;charset=utf8mb4',
            $settings['db_host'],
            (int) $settings['db_port'],
            $withDatabase ? ';dbname='.$settings['db_database'] : ''
        );

        // Cast the password to string first. Validation allows null (a blank
        // MySQL root password is legitimate on a local box), and passing null to
        // PDO is a TypeError, not an empty credential.
        return new \PDO($dsn, (string) $settings['db_username'], (string) ($settings['db_password'] ?? ''), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    /**
     * Guard the settings independently of the form rules.
     *
     * The wizard validates, but this service must be safe when called directly.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertSettingsUsable(array $settings): void
    {
        // db_password is the one setting allowed to be null: ConvertEmptyStringsToNull
        // turns an empty MySQL root password into null, and a blank root password is a
        // legitimate configuration on a local box. Rejecting null here made every such
        // attempt fail with "Could not connect" even though the credentials were right.
        foreach (['db_host', 'db_port', 'db_database', 'db_username'] as $key) {
            if (! array_key_exists($key, $settings) || ! is_scalar($settings[$key])) {
                throw new \InvalidArgumentException("Missing or invalid database setting: {$key}.");
            }
        }

        if (! array_key_exists('db_password', $settings)
            || ($settings['db_password'] !== null && ! is_scalar($settings['db_password']))
        ) {
            throw new \InvalidArgumentException('Missing or invalid database setting: db_password.');
        }

        // Defence in depth. Each of these is also a validation rule on the form,
        // but this method is the last gate before a value reaches a DSN or the
        // .env file, so it re-checks rather than trusting the caller.
        foreach (['db_host', 'db_database', 'db_username'] as $key) {
            if (preg_match('/[\r\n\x00`;\'"]/', (string) $settings[$key]) === 1) {
                throw new \InvalidArgumentException("The {$key} value contains a disallowed character.");
            }
        }

        $port = (int) $settings['db_port'];

        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('The database port must be between 1 and 65535.');
        }
    }

    /**
     * Create the administrator account.
     *
     * Written directly rather than by calling AdminSeeder, which reads
     * ADMIN_PASSWORD from config and deliberately refuses to create an account
     * without one. Creating it here keeps the operator's chosen password out of
     * `.env` entirely.
     *
     * @throws \RuntimeException
     */
    protected function createAdministrator(array $settings): void
    {
        try {
            $user = new User;
            $user->name = 'Administrator';
            $user->email = $settings['admin_email'];
            // 'password' is absent from User::$fillable, so it is assigned
            // directly; the 'hashed' cast hashes on assignment.
            $user->password = $settings['admin_password'];
            $user->email_verified_at = now();
            $user->is_active = true;
            $user->save();

            // The Super Admin role is seeded immediately above; without it the
            // account cannot reach the back office at all.
            $role = Role::where('name', 'Super Admin')->first();

            if ($role === null) {
                throw new \RuntimeException('The Super Admin role was not seeded, so the administrator has no permissions.');
            }

            // assignRole() looks up the role by name, not by key, so pass the
            // name. Passing the key silently no-ops because no role has that
            // name, leaving the administrator with no permissions at all.
            $user->assignRole('Super Admin');
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new \RuntimeException('The administrator account could not be created: '.$this->firstLine($e->getMessage()));
        }
    }

    /**
     * Generate a Laravel-compatible application key.
     *
     * Uses the framework's own generator so the encoding matches what
     * `php artisan key:generate` would have produced.
     */
    protected function generateApplicationKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * Re-read the freshly written .env so the rest of this request sees it.
     *
     * Without this the process keeps the values it booted with — which, on an
     * un-installed application, are the defaults rather than what was just
     * written — and migrate would target the wrong database.
     */
    protected function reloadConfiguration(): void
    {
        try {
            Artisan::call('config:clear');
        } catch (Throwable $e) {
            report($e);
        }

        // Re-bootstrap configuration from the freshly written .env FIRST, so the
        // new DB manager resolves against the new credentials. Forgetting the
        // 'config' instance is not enough: it was registered via instance(),
        // so removing it leaves no binding to re-resolve and every later
        // config() call throws "Target class [config] does not exist".
        // The Env repository is an immutable singleton, so re-running
        // LoadEnvironmentVariables alone would not pick up the new .env. Reset it
        // via reflection first.
        $reflection = new \ReflectionClass(Env::class);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue(null, null);

        // Re-read .env first, then rebuild config from it.
        app()->bootstrapWith([
            LoadEnvironmentVariables::class,
            LoadConfiguration::class,
        ]);

        // Now purge the resolved DB instance so the connection is rebuilt from
        // the new .env rather than served from the container.
        app()->forgetInstance('db');
        DB::purge();
    }

    /**
     * First line of a message, for display next to a log entry.
     *
     * Exception messages are single-line in practice but PDO has been known to
     * embed newlines, and this string reaches a view.
     */
    protected function firstLine(string $message): string
    {
        $line = trim(Str::of($message)->explode("\n")->first() ?? '');

        return Str::limit($line, 200, '');
    }
}
