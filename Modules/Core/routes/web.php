<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\WebApplicationController;

Route::get('sw.js', [WebApplicationController::class, 'serviceWorker'])
    ->name('web.service-worker');

// This shell contains no account data and is the only HTML precached for offline use.
Route::get('offline', [WebApplicationController::class, 'index'])
    ->name('web.offline');

// Do not let SPA navigation swallow API, installer, download or missing asset responses.
Route::get('/{path?}', [WebApplicationController::class, 'index'])
    ->where('path', '(?:admin|auth|employee|driver|online-menu|table-order)(?:/.*)?')
    ->name('web.application');
