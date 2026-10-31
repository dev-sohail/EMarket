---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Blade columns must match the table; no pluck('getKey')
Columns must match the table exactly: `categories._id`, not `categories.id` (no table has an `id`), `customer_addresses.address_line_1`, `order_addresses.postal_code`, `brands.website`, `discount_campaigns` has no `description`. Verify a suspicious name against information_schema.COLUMNS on MySQL before trusting it — the SQLite suite does not catch a wrong column name reliably. Also: never build a selected-ids list with `Collection::pluck('getKey')`; pluck reads attributes, not methods, so it silently yields nulls and every checkbox renders unchecked — use `->map->getKey()->all()`. A Blade `{{-- --}}` comment inside an `@php` block is a parse error; use `//` there.

## Storefront payment/shipping copy must be driven by the database, not hardcoded
Never hardcode a payment method, shipping method or feature toggle into a storefront view. components/store/footer.blade.php shipped a literal "COD" badge and "Pay when you receive", so deactivating the cod method in the admin left the storefront still promising Cash on Delivery while CheckoutController only offers PaymentMethod::active() — the footer and the checkout disagreed. Same class of bug: the footer interpolated `default_country`, a two-letter code, rendering "across GB" and implying COD availability was governed by that setting, which nothing enforces. Query the model with the same scopes the functional path uses (PaymentMethod::active()->ordered()) and render an explicit empty state, so an operator toggling a method in the admin sees the storefront change. Guarded by tests/Feature/StoreFooterPaymentMethodsTest.php.

## CKEditor needs a submit sync, and error summaries belong only in components/admin/alerts
CKEditor 4 replaces a <textarea> with its own widget and keeps typed content in memory, so unless it copies that content back before the browser serialises the form, the field is absent from the POST and `required` rejects it — the operator sees "Product description is required" for a description they just typed, with nothing wrong server-side (the same body creates the record, and old('description') round-trips fine). Six admin screens (product, category, page, blog, brand) each called CKEDITOR.replace() with no sync. layouts/admin.blade.php now owns ONE delegated capture-phase submit listener that calls updateElement() on every CKEDITOR.instances entry; do not add per-page copies. Second trap in the same area: do not hand-roll an $errors->any() summary inside a form partial. layouts.admin already renders every message through components/admin/alerts, so a local banner shows "There is 1 problem with this form." twice (product/partials/form.blade.php and category/partials/form.blade.php both had one). Field-level @error(...) next to the input is still correct and is not duplication. Guarded by tests/Feature/AdminProductDescriptionWiringTest.php.

## Escape script tags inside JS comments in Blade
Tests assert every inline <script> carries nonce="{{ $cspNonce }}" by regex-matching rendered HTML, and the regex matches a literal "<script>" written inside a JS block comment even though the browser treats script-block content as raw text. Escape both tags in prose comments as <\script> and <\/script>. Guarded by AdminAffiliateChartTest::test_every_inline_script_carries_the_response_nonce and ContentSecurityPolicyTest.
