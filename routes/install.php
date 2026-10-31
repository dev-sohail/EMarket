<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installation Routes
|--------------------------------------------------------------------------
|
| Registered unconditionally, but every route sits behind
| EnsureInstallerIsAvailable, which returns 404 the moment
| storage/installed.lock exists. The route file is always loaded rather than
| loaded conditionally for one specific reason: `route:cache` snapshots the
| route table to disk, so if these routes were absent at cache time they would
| stay absent, and deleting bootstrap/cache/routes-*.php would be the only way
| to bring the installer back. Keeping them registered and gating them with
| middleware means the lock is enforced on every request regardless of what the
| cached route table happens to contain.
|
| These deliberately do NOT use the `web` group. That group prepends
| TrackAnalytics, which queries analytics_visitors on every GET and would throw
| against a database that does not exist yet. The middleware below are the
| session/CSRF minimum the forms need, with UseInstallerSession ahead of
| StartSession to force the file driver.
|
*/

Route::middleware('installer')->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');

    Route::post('/test-connection', [InstallController::class, 'testConnection'])
        ->name('test-connection');

    Route::post('/', [InstallController::class, 'store'])->name('store');
});

// The completion page is deliberately outside the installer group. By the time
// it is reached the marker exists, so EnsureInstallerIsAvailable would abort
// with a 404 — and a redirect to a 404 is a broken install.
Route::get('/install/complete', [InstallController::class, 'complete'])->name('install.complete');
