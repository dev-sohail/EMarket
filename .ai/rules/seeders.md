---
paths:
  - 'database/seeders/**'
  - 'database/seeders/*.php'
---

# Seeders

## Seeded emails must be RFC-valid — no underscores in the domain
Seeded email addresses must be RFC-valid — a domain may not contain an underscore. "admin@poweredin_peptides.com" and "info@poweredin_peptides.com" both fail the `email` rule (Admin\Auth\LoginController validates `required|email`; SettingController validates store_email as `nullable|email`), so the admin could never sign in and settings could not be re-saved. Correct forms: admin@poweredinpeptides.com, info@poweredinpeptides.com, info@poweredin-peptides.com. Diagnosing this is slow because a validation failure looks identical to a bad password from the outside: both 302 back to the login form with no visible error. Decode the sessions.payload row (base64 JSON) — `errors.default.messages` holds the real reason.

## Read the seeded admin password from config, and never fall back to a known one
AdminSeeder must read the admin password from `config('admin.seed_password')` (which wraps `env('ADMIN_PASSWORD')` in config/admin.php), never from `env('ADMIN_PASSWORD')` directly. With a cached bootstrap/cache/config.php Laravel skips .env loading entirely, so `env()` returns null and the seeder would reset the admin account to a known fallback password. Two traps stacked here: (1) reading env() directly, and (2) guarding with `! $password` plus a local/testing exemption — an empty `ADMIN_PASSWORD=` passes `! $password` as falsey-check-miss only because the app env is `local`, so it still produced a `password` account. Use `blank($password)` and throw unconditionally before any write. Because config is cached, changing ADMIN_PASSWORD in .env requires `php artisan config:clear && php artisan config:cache` before the next seed sees it. phpunit.xml supplies its own ADMIN_PASSWORD so the guard never trips in tests.
