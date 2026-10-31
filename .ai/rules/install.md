---
paths:
  - 'app/Services/Install/**'
  - app/Services/Install/EnvFileWriter.php
---

# Install

## Installer trigger needs BOTH no marker and no .env
"Needs install" means storage/installed.lock is absent AND .env is absent — both, not just the marker. Requiring only the marker means deploying to a site that predates the marker (or one that lost it) redirects every request to an installer that then 404s to avoid clobbering the real config: a total outage caused by the safety feature. An existing .env is positive evidence to the contrary. InstallState::locked() also fails CLOSED (any filesystem error ⇒ installed). Never gate on database reachability — a MySQL blip must not unlock a write-capable endpoint.

## .env writer must reject newlines, and must not chmod 0600
Values written to .env come off an HTTP form and dotenv is line-oriented, so a newline would terminate the key/value pair and inject arbitrary settings (APP_KEY, APP_DEBUG, DB_DATABASE) — RCE via config injection. Escape is not available (dotenv has no round-trip-safe escape and a password may contain almost anything), so values are REJECTED: no \r \n \0, no double quote, no leading comment marker, checked for every value regardless of field. Never chmod .env to 0600 — Apache does not own the checkout, so 0600 makes every request fail with MissingAppKeyException; mirror the containing directory's group and use 0660.
