---
paths:
  - 'tests/**/*.php'
---

# Tests

## Never run php artisan test while config/route caches exist - it wipes MySQL
Running `php artisan test` directly (not `composer test`) DESTROYS the seeded MySQL database. Reason: a cached `bootstrap/cache/config.php` makes Laravel skip `.env` loading and build the container from the cached array, so `database.default` stays `mysql` and phpunit.xml's `DB_CONNECTION=sqlite` is ignored (even with force="true", because a cached config bypasses env entirely). RefreshDatabase then runs `migrate:fresh` on the real database and drops every table. A cached routes file has a second effect: the DB-registered API routes get frozen, so AdminOrdersApiTest/StoreCatalogApiTest fail with 401/404 against the test schema. `composer test` now clears BOTH caches first. `Tests\TestCase::setUp()` also refuses to run when the cached config pins a non-sqlite/:memory: connection, and it must read `bootstrap/cache/config.php` by hand because the container does not exist until `parent::setUp()`, and RefreshDatabase has already migrated by then. Verified: before the guard, two runs emptied users/settings/pages/api_endpoints; after, the same run aborts and the data survives.


## Test guard and safe execution
- The TestCase guard (which refuses to run when `bootstrap/cache/config.php` pins a non-sqlite/:memory: DB) runs before `parent::setUp()`. This is critical because RefreshDatabase may have already attempted migration setup in the parent's flow in cached environments; the guard must read `bootstrap/cache/config.php` directly (the container may not exist yet).
- Always use `composer test` (which clears caches first). Never run `php artisan test` directly while `bootstrap/cache/config.php` exists.
- Tests require in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) in `phpunit.xml`; do not rely on local .env when caches are present.