---
paths:
  - routes/install.php
---

# Routes

## Install routes: always registered, own middleware group, before /{page}
Install routes are registered unconditionally and gated by EnsureInstallerIsAvailable, never conditionally registered: route:cache snapshots the table, so routes absent at cache time stay absent and deleting bootstrap/cache/routes-*.php would be the only way to restore the wizard. They must NOT use the `web` group — TrackAnalytics queries analytics_visitors on every GET and that table does not exist pre-install; UseInstallerSession forces the file session driver because config/session.php defaults to `database`. Registered BEFORE the `/{page}` catch-all in bootstrap/app.php's then() block, which otherwise shadows /install and sends it to PageController.
