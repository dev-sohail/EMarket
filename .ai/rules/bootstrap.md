---
paths:
  - 'bootstrap/**'
  - bootstrap/app.php
---

# Bootstrap

## Serve at domain root only — sub-directory URLs 405 on / with route cache
Never serve this app from a URL sub-directory (http://host/swiss-ecom/poweredin/public/). Laravel 13's CompiledRouteCollection::requestWithoutTrailingSlash() trims the trailing slash off REQUEST_URI, which stops Symfony stripping /index.php and makes pathInfo become the directory itself instead of /. With route:cache active this makes GET / throw MethodNotAllowedHttpException (405) while every other route works — an easy trap to misread as a stale cache. Fix: serve at the document root via vhost poweredin-app.local -> /opt/lampp/htdocs/swiss-ecom/poweredin/public (config in /opt/lampp/etc/extra/httpd-vhosts.conf). If you must use a sub-directory, leave route:cache off.

## Boot needs a stable temporary APP_KEY when .env is absent
Without an APP_KEY the framework cannot complete even an error response: Kernel::terminateMiddleware() re-resolves every middleware after the response is sent, StartSession resolves the encrypter, MissingAppKeyException is thrown with headers already sent — visitor gets a 500 and a fatal. bootstrap/app.php therefore supplies a temporary key ONLY when APP_KEY is genuinely absent in $_SERVER/$_ENV/getenv (the .env file is not read yet at that point, so env() is not trustworthy there). It must be STABLE across requests (persisted to storage/framework/bootstrap-temporary-key) or the installer's CSRF token round-trip fails with 419. Delete that file after installing.
