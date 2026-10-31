<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run the suite against a real database, before the container
     * exists and therefore before anything can migrate or truncate it.
     *
     * Why this exists, and why it reads the cache file by hand:
     *
     *  - `force="true"` on the DB_CONNECTION entries in phpunit.xml only helps
     *    while there is no cached config. A cached `bootstrap/cache/config.php`
     *    makes Laravel skip `.env` loading entirely and build the container from
     *    the cached array, so the resolved connection stays `mysql` no matter
     *    what the environment says.
     *  - The container does not exist yet at this point — it is built inside
     *    `parent::setUp()` — so `config()` is unusable here. Reading the cached
     *    array directly is the only way to know what the run would connect to.
     *  - `RefreshDatabase` calls `migrate:fresh` from inside `setUp()`, so any
     *    check that runs after `parent::setUp()` is already too late to protect
     *    the data.
     *
     * This check stopped `php artisan test` twice from running `migrate:fresh`
     * against the real MySQL database and dropping every seeded table.
     */
    protected function setUp(): void
    {
        $this->guardAgainstDestructiveDatabaseAccess();

        parent::setUp();
    }

    private function guardAgainstDestructiveDatabaseAccess(): void
    {
        // Nothing to police: the run was not started by the test runner.
        if (env('APP_ENV') !== 'testing') {
            return;
        }

        $cache = __DIR__.'/../bootstrap/cache/config.php';

        if (! is_file($cache)) {
            // No cached config, so phpunit.xml's forced sqlite/:memory: applies.
            return;
        }

        $cached = require $cache;

        $default = $cached['database']['default'] ?? null;
        $name = $cached['database']['connections'][$default]['database'] ?? null;

        if ($default !== 'sqlite' || $name !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Refusing to run: bootstrap/cache/config.php pins database.default to "%s" / database "%s", '
                    .'but the suite must run against in-memory SQLite. With a cached config Laravel ignores the '
                    .'environment, so RefreshDatabase would run migrate:fresh on the real database and drop every '
                    .'table. Run `composer test`, which clears the config cache first.',
                is_string($default) ? $default : get_debug_type($default),
                is_string($name) ? $name : get_debug_type($name),
            ));
        }
    }
}
