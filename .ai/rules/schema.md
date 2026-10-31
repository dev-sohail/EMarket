---
paths:
  - 'database/schema/**'
---

# Schema

## schema:dump fails on this MariaDB; rebuild dump manually or fresh setups break
php artisan schema:dump is BROKEN on this box: MySqlSchemaState::dump() passes --routines to mysqldump, and this MariaDB was upgraded without mysql_upgrade, so it fails with "Column count of mysql.proc is wrong (1558)". Because the failure happens on step 1, Laravel never reaches appendMigrationData(), so the written dump has the tables but NO INSERTs into `migrations`. A fresh `migrate` then loads that dump and every migration collides with "table already exists". To regenerate correctly, run mysqldump yourself with --skip-routines for the --no-data pass, strip AUTO_INCREMENT=, then append: mysqldump <conn> migrations --no-create-info --skip-extended-insert --skip-routines --compact --complete-insert. Always verify by loading the dump into a scratch DB and confirming `migrate` says "Nothing to migrate".

## Inspect migration source for schema truth
Because tests run on in-memory SQLite (which normalizes types), and because schema dumps may be unavailable or broken in this environment, always inspect migration source (`database/migrations/*`) to determine the authoritative MySQL column types, constraints, and indexes. Do not rely on SQLite's schema representation or on missing dumped schema files.
