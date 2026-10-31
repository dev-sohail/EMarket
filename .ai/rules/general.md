---
paths:
  - '**/*.php'
  - vite.config.js
  - .env.example
---

# General

## config:cache is on — editing .env has no effect until you re-cache
This project runs with config:cache ON, so Laravel never loads .env at runtime — editing .env changes nothing until you re-run `php artisan config:cache`. Any admin warning of the form "set X in .env" (e.g. the DashboardController INVENTORY_RESERVATION_TTL_MINUTES notice) will keep showing after you edit .env, because config/app.php was already frozen into bootstrap/cache/config.php. Same trap hits AdminSeeder, which reads env('ADMIN_PASSWORD'). Workflow: edit .env -> php artisan config:cache -> re-apply perms (sudo chgrp -R www-data storage bootstrap/cache; sudo chmod -R g+rwX ...; sudo find storage bootstrap/cache -type d -exec chmod g+s {} +). Verify with `grep <key> bootstrap/cache/config.php`, not with the .env file.

## Font/icon URLs are baked at build time — rebuild after changing APP_URL
laravel-vite-plugin/fonts bakes absolute font URLs into public/build/fonts-manifest.json at BUILD time, and Vite bakes the base into the CSS url() for webfonts (Font Awesome). Changing APP_URL or removing ASSET_URL therefore does nothing until `npm run build` is re-run — every page keeps requesting the old path and the webfonts 404. Symptom is silent: no CSS error, just blank Font Awesome glyphs and "downloadable font: download failed" in the console, plus preload warnings. Always verify the URL *inside* the built CSS (`grep -oE 'url\([^)]*\.woff2?\)' public/build/assets/app-*.css`) and the rendered page, not just that the font file exists under public/build/assets — fetching the file by hand proves nothing if nothing references that path.

## Quote APP_NAME and never nest vars in MAIL_FROM_ADDRESS
dotenv rejects unquoted spaces in APP_NAME, which makes the whole .env unparseable and every artisan call dies at ApiRouteRegistrar's boot-time api_endpoints query. Nested interpolation like "${APP_NAME@${APP_URL}}" is NOT supported either and resolves to a literal string, so MAIL_FROM_ADDRESS throws an RFC 2822 addr-spec error that RegisterController's catch-all turns into a generic redirect failure. Use APP_NAME="Two Words" and a plain literal for MAIL_FROM_ADDRESS.
