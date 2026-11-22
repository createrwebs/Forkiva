<?php

use Modules\Export\Http\Controllers\Api\V1\ExportController;

Route::controller(ExportController::class)
    ->prefix('exports')
    ->name('api.v1.exports.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/{resource}', 'store')->name('store');
        Route::get('/{id}', 'show')->name('show');
        Route::get('/{id}/download', 'download')->name('download');
    });
