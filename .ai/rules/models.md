---
paths:
  - app/Models/Setting.php
  - 'app/Models/**/*.php'
  - app/Models/ApiEndpoint.php
  - 'app/Models/*.php'
---

# Models

## Upsert settings with Setting::put(), never updateOrCreate on group+key
`settings.key` carries a global UNIQUE index (`settings_key_unique`); `group` is only a non-unique display label, because `setting()` resolves a value with `pluck('value','key')`. So a setting's identity is `key` ALONE. Never write `Setting::updateOrCreate(['group' => ..., 'key' => ...], ...)` — the search includes `group`, so any row whose stored group differs from the caller's expected group misses the lookup and Eloquent falls back to an INSERT that violates the unique index. That is a 500 `Duplicate entry '<key>' for key 'settings_key_unique'`, and it only fires once an older seeder has written a different group than the controller expects (shipping: `timezone` seeded as group `timezone` vs looked up as `general`; `returns_enabled` seeded as `return` vs `review`). Use `Setting::put($key, $value, $group)`, which searches on `key` and writes `group` as a value so each save re-normalises drift. Guarded by tests/Feature/SettingKeyIdentityTest.php. Related: `value` is cast `json`, and `setting_forget()` is required after any write or the 5-minute cache hides it.

## Encrypted casts need a text column - char(255) overflows on real Stripe keys
An `encrypted` cast needs a `text` column, never `char(255)`/`varchar(255)`. Laravel base64-encodes a JSON envelope of {iv, value, mac, tag}, so the ciphertext is ~1.4x the plaintext plus ~170 bytes fixed: a 107-char Stripe test key stored as 372 chars, and saving it in the admin 500'd with `SQLSTATE[22001] ... Data too long for column 'stripe_secret_key'` under STRICT_TRANS_TABLES. The mistake is sizing the column from the validated input length — PaymentMethodController validates `max:255` on the *plaintext*, while the column receives the *ciphertext*, and those measure different things. Corollary trap: a schema assertion in a feature test is useless here, because the suite runs on SQLite where `->text()` produces `text` no matter what MySQL holds — inspect migration source (e.g. `database/migrations/*`) to verify actual MySQL column types, or assert based on migration definitions, not SQLite's in-memory schema. Guarded by tests/Feature/EncryptedColumnWidthTest.php.

## API endpoint actions must be on the allowed_handlers allow-list
`action` is user-supplied and resolveHandler() passes it to app() then invokes any public method on it — arbitrary method invocation (RCE behind an admin login) if unrestricted. Every action must appear in config('api_management.allowed_handlers'); the check is an exact string match, so an allow-listed class paired with an unreviewed method is still refused, and a missing/null key denies everything rather than allowing it. Add an entry per endpoint deliberately — no wildcards, they restore the hole. StoreApiEndpointRequest::rules() mirrors the list so the form rejects a bad action early, but the runtime check is the boundary.

## User password is not fillable and NOT NULL — never create() with it
'password' and 'email_verified_at' are NOT on User::$fillable on purpose. Users.password is NOT NULL, so never use User::create(['password' => ...]) — create() INSERTs before an assignment could happen; build `new User([...])`, assign `$user->password = $plaintext`, then save() once. Prefer forceFill() for deliberate non-fillable writes (RegisterController::verify, AdminSeeder).
