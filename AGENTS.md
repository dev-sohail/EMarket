# AGENTS.md

Laravel 13 e-commerce app (PHP 8.5). Storefront + admin + Stripe + a dynamic API-management module.

## Read before you edit anything

- `.ai/rules/index.md` maps path globs to area rule files. Read every rule file whose globs cover your target path, plus `general.md` (applies to all `**/*.php`). These are hard-won traps — skipping them repeats real bugs.
- `composer show --direct` / `package.json` for installed versions; do not assume an API.

## Commands

| Task | Command |
| --- | --- |
| Serve | `composer run dev` (runs `php artisan dev`) or `php artisan serve` |
| Build frontend | `npm run build` |
| Dev frontend | `npm run dev` |
| Fresh setup | `composer setup` (install, env, key, migrate, npm install, build) |
| Run tests | `composer test` (= `config:clear` + `php artisan test`) |
| One test file | `composer test -- --filter=...` or `php artisan test --filter=... tests/Feature/XxxTest.php` |
| Format PHP | `vendor/bin/pint --dirty --format agent` (never `--test`) |
| Route list | `php artisan route:list` — but see "Dynamic API routes" below |

Tests run on **in-memory SQLite** (`phpunit.xml`). Production uses **MySQL** (`poweredin_peptides`). SQLite silently resolves unknown quoted columns to string literals, so a green suite does not prove a query works on MySQL. 

**Never run `php artisan test` while `bootstrap/cache/config.php` exists.** `composer test` clears caches first; running `php artisan test` directly can bypass the test DB config and wipe the real MySQL database (see `.ai/rules/tests.md`).

## Architecture

- All models extend `App\Models\PrefixedModel`: primary key is a string `_id`, **no table has an `id` column**, `$incrementing = false`. Use `$model->getKey()` / `->getRouteKey()`, never `->id`.
- `AppServiceProvider` binds route params: admin routes resolve by `_id`, store routes by `slug` for product/category/brand/page, `_id` otherwise.
- `app/Services/**` holds the domain logic (Catalog, Checkout, Inventory, Order, Pricing, …). Controllers are thin.
- `config/api_management.php` + `ApiManagementServiceProvider` register API routes **from the database at boot**. See `.ai/rules/index.md` for area rules; API/provider/reflection specifics are documented in the relevant rule files if present.
- Scheduled: `app:expire-reservations` hourly. Stripe webhook at `/stripe/webhook` (CSRF-exempt).

## Quirks that bite

- **Sessions**: `SESSION_DRIVER=database`. `sessions.id` is a string PK (migration `2026_09_30_000000`); this is a database table constraint, not a model trait - models still follow the string `_id` convention above. Auto-increment bigint breaks every login (419s).
- **Route caching is real**: new/changed `api_endpoints` rows take effect only after `php artisan route:cache` (or `route:clear`). `APP_ENV=local` does not disable it — presence of `bootstrap/cache/routes-*.php` is what counts. A stale routes file made `/` return 405.
- **Apache strips the `Authorization` header** on this machine; use `X-API-TOKEN: <token>` for admin API calls locally. Fix needs a server-side `.htaccess` change, not an app change.
- **CSP**: no inline `<script>` or `on*` handlers in Blade. Drive UI from `resources/js/app.js` via `data-action`/`data-args` (JSON, double-quoted). `nonce="{{ $cspNonce }}"` on the rare inline script that is allowed.
- **`setting()` swallows `Throwable`** and returns the default — a broken settings layer looks like "not configured". If a setting does nothing, check the `settings` table, its casts, and the relevant cache (`settings`/cache_locks) before assuming code is wrong. Also remember that writes must invalidate caches (see `.ai/rules/models.md`).
- **`Cache::lock()` needs `cache_locks.owner`**: `Illuminate\Cache\DatabaseLock` writes `owner`, but this DB shipped with `owner_id` (NOT NULL, no default). Under STRICT_TRANS_TABLES every lock insert was rejected, `acquire()` returned false forever, and `CheckoutService`'s `block(30)` timed out — so every checkout showed the generic "We encountered an error while processing your order" page. `block()` **throws** `LockTimeoutException`; it never returns false, so a `if (! $lockAcquired)` branch is dead code. Any DB whose `cache_locks` has extra NOT NULL columns without defaults has the same failure.
- **A shipping rule priced `0.00` does not apply**: `ShippingRule::calculateRate()` treats `price = 0.00` as "no rate" (it is what an untouched admin numeric input persists), not as free shipping. A zone whose only rule is priced 0.00 therefore offers **no** method, and checkout refuses the order with "The selected shipping method is no longer available". Free shipping is either a `free_shipping_threshold > 0`, or no rule at all for that method (the method's `base_cost` then applies). A city that is on no zone's list also fails unless a zone has no cities at all (catch-all) or `config('shipping.default_zone')` is set.
- **Mass delete skips model events** (`whereIn()->delete()`). Delete one-by-one when a `deleting` hook must run.
- **`Rule::in()` needs an array**, not a comma-quoted string.
- **`Rule::unique()->ignore()`** must receive the model instance or `($key, '_id')` — bare key defaults to an `id` column that does not exist.
- **Pivots have no FK/cascade**; detach in a `deleting` hook (not the controller).

- **Return/cancel paths must be reversible**: when releasing resources (locks, reserved inventory), ensure cleanup runs on both success and failure/cancel paths. Prefer try/finally or explicit cleanup; returning early without releasing held locks or restoring reservations will leak state between requests.

## Frontend

- Vite + Tailwind v4 via `@tailwindcss/vite`. Entry: `resources/css/app.css`, `resources/js/app.js`.
- If a UI change does not appear, run `npm run build` (or `npm run dev`).

## Verification order

`vendor/bin/pint --dirty --format agent` → `composer test`. Run the narrowest test that covers the change (`--filter=` or a file path). If running tests directly, ensure caches are cleared first. Never run `php artisan test` while `bootstrap/cache/config.php` exists.