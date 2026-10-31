---
paths:
  - 'storage/**'
---

# Storage

## Web user is www-data; storage must be group-writable by www-data
Apache runs as daemon:daemon but PHP-FPM (which executes the app) runs as uid 33 www-data. Any dir the web user writes to must be dev-so:www-data mode 2775 (g+w + setgid), else Blade compile dies with "tempnam(): file created in the system's temporary directory" 500. This covers storage/framework/{views,cache}, storage/logs, storage/app, storage/pail and bootstrap/cache. Fix with: sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R g+rwX storage bootstrap/cache && sudo find storage bootstrap/cache -type d -exec chmod g+s {} + . A green CLI test run proves nothing here; verify with an actual curl to the public URL.

## storage/app/public must deny PHP execution, and .htaccess needs un-ignoring
storage/app/public is symlinked into the docroot as /storage, so anything written there is web-reachable. It carries an .htaccess that denies .php/.phtml/.phar/cgi/pl/py/shtml/jsp/asp/aspx via RemoveHandler + Require all denied (works because the vhost has AllowOverride All; php_flag would be a no-op under proxy_fcgi). `storage/app/public/.gitignore` needs `!.htaccess` or the guard is never committed — that file ignores `*` by default.
