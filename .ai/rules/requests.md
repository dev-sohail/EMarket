---
paths:
  - 'app/Http/Requests/**/*.php'
---

# Requests

## Never name a FormRequest helper user() — it collides with Request::user()
Never declare a private method named `user()` in a FormRequest — Illuminate\Http\Request already declares it public, so the class fatals at autoload time with "Access level to ...::user() must be public", which kills the whole PHPUnit process ("Premature end of PHP process") rather than failing one test. Name it targetUser(). Also note $this->user() returns the AUTHENTICATED user, so using it for a unique()->ignore() would exclude the wrong row.
