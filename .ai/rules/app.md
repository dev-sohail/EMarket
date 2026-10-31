---
paths:
  - 'app/**/*.php'
---

# App

## config(app.inventory.*) has no fallback — use low_stock_threshold()
config/app.php defaults INVENTORY_LOW_STOCK_THRESHOLD / INVENTORY_RESERVATION_TTL_MINUTES to '' so a fresh .env leaves them blank. `config('app.inventory.low_stock_threshold', 5)` does NOT fall back — the key exists with an empty string, bound as a parameter MySQL reads it as 0 (every low-stock query matched nothing) and SQLite reads it as 0 too but then reports *every* row. Always read it through the `low_stock_threshold(): ?int` helper in app/helpers.php and branch on null ("no low-stock reporting"), as DashboardController does.
