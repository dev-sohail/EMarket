---
paths:
  - 'database/migrations/**/*.php'
---

# Migrations

## MySQL enum columns are enforced; SQLite is not
MySQL enforces `enum` columns; SQLite (the test DB) does not, so a migration whose enum disagrees with the PHP enum writing it stays green in tests and 500s in production under STRICT_TRANS_TABLES. inventory_movements.type was enum('in','out','adjustment') while InventoryMovementType writes stock_in/sale/return/adjustment/damage/correction/reserved/released — every stock adjustment failed on insert. When adding a PHP-backed enum column, mirror its cases in the migration and verify with information_schema.COLUMNS plus a rolled-back insert on MySQL, not just php artisan test.
