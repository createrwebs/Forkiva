<?php

use Illuminate\Support\Facades\Route;
use Modules\Installer\Http\Controllers\InstallerController;
use Modules\Installer\Http\Controllers\InstallerPageController;
use Modules\Installer\Http\Middleware\CheckInstallation;

// The module's web route group supplies cookies, sessions, and CSRF protection.
Route::prefix('installer')
    ->middleware(CheckInstallation::class)
    ->withoutMiddleware('checkInstalled')
    ->group(function () {
        Route::controller(InstallerController::class)->group(function () {
            Route::get('status', 'status');
            Route::post('checks', 'checks');
            Route::post('configuration', 'configuration');
            Route::post('database', 'database');
            Route::post('process', 'process');
            Route::post('admin', 'admin');
            Route::post('finish', 'finish');
        });

        Route::get('/{page?}', [InstallerPageController::class, 'index'])
            ->where('page', 'requirements|permissions|configuration|database|admin|finish')
            ->name('installer.welcome');
    });
