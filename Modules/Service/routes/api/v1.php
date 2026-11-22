<?php

use Modules\Service\Http\Controllers\Api\V1\ServiceController;

Route::controller(ServiceController::class)
    ->prefix('services')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.services.index');
        Route::get('/{category}/{service}', 'show')->middleware('can:admin.services.index');
        Route::put('/{category}/{service}/settings', 'update')->middleware('can:admin.services.edit');
    });
