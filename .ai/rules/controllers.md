---
paths:
  - 'app/Http/Controllers/**/*.php'
---

# Controllers

## Blank form input arrives as null, not ''
The web middleware group runs ConvertEmptyStringsToNull, so a left-blank form field reaches the controller as null, never as ''. Any "keep the stored value when the input is blank" branch must test with `filled($input)`, not `$input !== ''` — the latter is true for a blank input and silently writes null over the stored value. This destroyed the Stripe secret key on every payment-method save until it was fixed.

## Never echo $e->getMessage() to a client — report() it
Never put `$e->getMessage()` in a response body or flash message. A QueryException carries the full SQL statement, bound values, table/column names and driver text; on a pre-auth endpoint (e.g. RegisterController's catch-all) that renders into the page. Use report($e) + static copy, as Ajax\ReviewController does. Passing an InvalidArgumentException message through is fine — the narrow catch means a QueryException cannot reach that branch.
