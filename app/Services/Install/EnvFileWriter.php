<?php

namespace App\Services\Install;

use Illuminate\Filesystem\Filesystem;

/**
 * Writes the `.env` file from installer input.
 *
 * The whole security of this class is in `assertSafeValue()`: every value that
 * reaches the file is attacker-controlled (it came off an HTTP form), and dotenv
 * is a line-oriented format. A value containing a newline would let a visitor
 * append arbitrary keys — `APP_DEBUG=true`, a second `APP_KEY`, a `DB_DATABASE`
 * pointing at a database they control — to a file the application reads on every
 * request. Injection into a config file is remote code execution by another
 * name, so the guard is a hard allow-list on characters, not escaping.
 *
 * Escaping is not an option either: dotenv has no escape syntax that survives
 * being read back by `env()`, and a database password is allowed to contain
 * almost anything. So values are rejected instead of transformed, and the
 * messages tell the operator exactly which character was the problem.
 */
class EnvFileWriter
{
    /**
     * Characters permitted in a database name.
     *
     * MySQL identifiers are far more permissive than this in general, but the
     * installer only ever needs to name a database it is about to create, and a
     * tight set means a typo is caught here rather than as a SQL syntax error
     * later.
     */
    public const DATABASE_PATTERN = '/^[A-Za-z0-9_]+$/';

    /**
     * Hostnames, IPv4 literals, and `localhost`. IPv6 is excluded because the
     * bracket syntax collides with the value being written into a URL-ish field.
     */
    public const HOST_PATTERN = '/^[A-Za-z0-9._-]+$/';

    /**
     * MySQL usernames. Kept tight for the same reason as the database name.
     */
    public const USERNAME_PATTERN = '/^[A-Za-z0-9._@\-]+$/';

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * Validate the database settings the operator submitted.
     *
     * Returns a map suitable for `validate()`, so failures surface as ordinary
     * field errors on the form rather than an exception.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'db_host' => ['required', 'string', 'max:255', 'regex:'.self::HOST_PATTERN],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:64', 'regex:'.self::DATABASE_PATTERN],
            'db_username' => ['required', 'string', 'max:128', 'regex:'.self::USERNAME_PATTERN],
            // No pattern: a password may legitimately contain punctuation and
            // spaces. It is still rejected for newlines, quotes and equals —
            // see assertSafeValue().
            //
            // nullable rather than 'present': ConvertEmptyStringsToNull turns an
            // empty MySQL root password into null before validation runs, and
            // `present` + `string` rejects null outright. A blank root password
            // is a legitimate (if unwise) configuration on a local box, and the
            // rule below is what actually matters.
            'db_password' => ['nullable', 'string', 'max:255'],
            'app_url' => ['required', 'string', 'max:255', 'regex:#^https?://[^\s/]+#i'],
            'app_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:12', 'max:255'],
            'admin_password_confirmation' => ['required', 'same:admin_password'],
        ];
    }

    /**
     * Human-readable messages for the pattern rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'db_host.regex' => 'The database host may only contain letters, numbers, dots, dashes and underscores.',
            'db_database.regex' => 'The database name may only contain letters, numbers and underscores.',
            'db_username.regex' => 'The database username may only contain letters, numbers, dots, dashes, underscores and @.',
            'app_url.regex' => 'The application URL must start with http:// or https:// and contain no spaces.',
        ];
    }

    /**
     * Reject a value that would corrupt or inject into the dotenv file.
     *
     * The validation rules above already constrain host, database, username,
     * host and URL by pattern. This is the backstop for the two fields with no
     * pattern — the database password and the application name — and it runs on
     * every value regardless, so a future rule change cannot quietly reopen the
     * hole.
     *
     * @throws \InvalidArgumentException
     */
    public function assertSafeValue(string $key, string $value): void
    {
        // A newline is the whole attack: it terminates the key=value pair and
        // everything after it becomes a new setting.
        if (preg_match('/[\r\n\x00]/', $value) === 1) {
            throw new \InvalidArgumentException(
                "The {$key} value may not contain line breaks or null bytes."
            );
        }

        // A double quote inside a quoted value terminates it early; a bare `#`
        // after whitespace starts a comment and truncates the value.
        if (str_contains($value, '"')) {
            throw new \InvalidArgumentException("The {$key} value may not contain a double quote.");
        }

        if (preg_match('/^\s*#/', $value) === 1) {
            throw new \InvalidArgumentException("The {$key} value may not begin with a comment marker.");
        }
    }

    /**
     * Build the complete contents of the new `.env`.
     *
     * Written from a template rather than by editing `.env.example`, so the
     * installer does not depend on that file's contents and cannot inherit a
     * mistake from it. Values are quoted, and every one is passed through
     * assertSafeValue() first.
     *
     * @param  array<string, string>  $values
     *
     * @throws \InvalidArgumentException
     */
    public function render(array $values): string
    {
        foreach ($values as $key => $value) {
            $this->assertSafeValue($key, $value);
        }

        $lines = [
            'APP_NAME='.$this->quote($values['app_name']),
            'APP_ENV=production',
            'APP_KEY='.$this->quote($values['app_key']),
            // False, always. With debug on, any uncaught non-HTTP Throwable
            // renders a Symfony page containing APP_KEY, DB_PASSWORD and every
            // other secret in this file.
            'APP_DEBUG=false',
            'APP_URL='.$this->quote(rtrim($values['app_url'], '/')),

            'APP_LOCALE=en',
            'APP_FALLBACK_LOCALE=en',
            'APP_FAKER_LOCALE=en_US',

            'APP_MAINTENANCE_DRIVER=file',

            'BCRYPT_ROUNDS=12',

            'LOG_CHANNEL=stack',
            'LOG_STACK=single',
            'LOG_DEPRECATIONS_CHANNEL=null',
            'LOG_LEVEL=error',

            'DB_CONNECTION=mysql',
            'DB_HOST='.$this->quote($values['db_host']),
            'DB_PORT='.(string) (int) $values['db_port'],
            'DB_DATABASE='.$this->quote($values['db_database']),
            'DB_USERNAME='.$this->quote($values['db_username']),
            // Quoted and never logged; the `#` and quote guards above keep this
            // safe to embed regardless of what the operator chose.
            'DB_PASSWORD='.$this->quote($values['db_password']),

            'SESSION_DRIVER=database',
            'SESSION_LIFETIME=120',
            'SESSION_ENCRYPT=false',
            'SESSION_PATH=/',
            'SESSION_DOMAIN=null',

            'BROADCAST_CONNECTION=log',
            'FILESYSTEM_DISK=local',
            'QUEUE_CONNECTION=database',

            'CACHE_STORE=database',

            'MEMCACHED_HOST=127.0.0.1',

            'REDIS_CLIENT=phpredis',
            'REDIS_HOST=127.0.0.1',
            'REDIS_PASSWORD=null',
            'REDIS_PORT=6379',

            'MAIL_MAILER=log',
            'MAIL_SCHEME=null',
            'MAIL_HOST=127.0.0.1',
            'MAIL_PORT=2525',
            'MAIL_USERNAME=null',
            'MAIL_PASSWORD=null',
            // A plain literal. Nested interpolation such as
            // "${APP_NAME@${APP_URL}}" is NOT supported by dotenv: it resolves
            // to the literal string and every send fails with an RFC 2822
            // addr-spec error.
            'MAIL_FROM_ADDRESS='.$this->quote('hello@example.com'),
            'MAIL_FROM_NAME='.$this->quote($values['app_name']),

            '# Stripe (payment gateway). Configure in Admin > Settings > Payment Methods.',
            'STRIPE_ENABLED=',
            'STRIPE_SANDBOX=true',
            'STRIPE_PUBLISHABLE_KEY=',
            'STRIPE_SECRET_KEY=',
            'STRIPE_WEBHOOK_SECRET=',

            '# Inventory',
            'INVENTORY_LOW_STOCK_THRESHOLD=',
            'INVENTORY_RESERVATION_TTL_MINUTES=',

            '# Consumed by AdminSeeder on a later re-seed. Deliberately left empty:',
            '# the admin account is created by the installer with a password the',
            '# operator chose, and storing it here would keep a copy of a real',
            '# credential in a world-readable file.',
            'ADMIN_PASSWORD=',

            'VITE_APP_NAME='.$this->quote($values['app_name']),

            'TIMEZONE=UTC',
        ];

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * Write the environment file, refusing to clobber an existing one.
     *
     * The caller is expected to have already checked via
     * InstallState::environmentFileExists(); this repeats the check so the
     * guarantee holds even if that is skipped.
     *
     * @throws \RuntimeException
     */
    public function write(string $contents): void
    {
        $path = app()->environmentFilePath();

        if ($this->files->exists($path)) {
            throw new \RuntimeException(
                'Refusing to overwrite an existing .env file. Delete it yourself if you really mean to reinstall.'
            );
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);

        $this->applyRestrictivePermissions($path);
    }

    /**
     * Make the environment file as private as the server can actually support.
     *
     * Naively chmod'ing to 0600 breaks the site. On this host Apache does not run
     * as the owner of the checkout — the web server user cannot read a file that
     * only its owner can, so a 0600 `.env` makes every request fail with
     * "No application encryption key has been specified" and a stack trace.
     * That is exactly what happened here: tightening the mode to 0600 took the
     * whole storefront down until it was relaxed again.
     *
     * So: mirror the group of the directory the file lives in and allow group
     * read. The group is already the web server's (storage/ and bootstrap/cache/
     * are dev-so:www-data with group write), which is what every other path in
     * this project relies on. `chgrp` fails for a non-owner, so the file is left
     * in the directory's group and only the mode is adjusted.
     *
     * If the containing directory is not group-accessible there is nothing safe to
     * do from PHP — the operator has to fix ownership — so the mode is left alone
     * rather than guessed at.
     */
    protected function applyRestrictivePermissions(string $path): void
    {
        $directoryMode = @fileperms(dirname($path));

        if ($directoryMode === false) {
            return;
        }

        // Group rwx: the directory is shared with the web server user.
        if (($directoryMode & 0o070) !== 0o070) {
            return;
        }

        // rw-rw---- : owner and web-server group only. Never 0600 — the web
        // server is not the owner.
        @chmod($path, 0660);
    }

    /**
     * Wrap a value in double quotes.
     *
     * Safe because assertSafeValue() has already guaranteed the value contains
     * no double quote, no newline and no null byte — the only three characters
     * that could end the quoted string early or extend it.
     */
    protected function quote(string $value): string
    {
        // Inside double quotes dotenv treats `\` as an escape introducer and `$`
        // as the start of a `${VAR}` reference. A password such as `ab\cd` made
        // the whole file unparseable ("unexpected escape sequence") and bricked
        // the site after install, while `a\nb` or `${HOME}` silently changed the
        // credential. `\\` and `\$` are the two escapes the parser round-trips.
        return '"'.str_replace(['\\', '$'], ['\\\\', '\\$'], $value).'"';
    }
}
