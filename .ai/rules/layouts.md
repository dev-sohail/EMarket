---
paths:
  - 'resources/views/layouts/*.blade.php'
---

# Layouts

## Diagnose form POST bodies with logging, not HTTP status codes
CKEditor 4 replaces a <textarea> with its own widget and keeps typed content in memory, so the textarea must be updated or the POST carries no value and `required` reports the field missing. A submit-time sync alone is fragile: layouts/admin.blade.php registers a DOMContentLoaded handler that hooks CKEDITOR.on('instanceReady') to sync on every `change` AND registers a capture-phase submit sync as a backstop. The DOMContentLoaded wrapper matters — ckeditor.js loads after that block, and the layout's listener must run before the page's own CKEDITOR.replace(), so the instanceReady hook is in place before any editor is created. Diagnostic that settled where the value went: log the submitted keys in StoreProductRequest when description is missing, then read storage/logs/ — a 302 with an identical byte size every attempt tells you nothing about the body. HTTP 302 on a failed POST is ambiguous; always follow the redirect or read `old()`. Silent truncation is a separate trap: short_description has maxlength=500 and the browser eats the overflow with no warning, which once sent a 600-char description into the wrong field.
