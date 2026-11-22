<?php

use Modules\Translation\Http\Controllers\Api\V1\TranslationController;

Route::controller(TranslationController::class)
    ->prefix('translations')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.translations.index');
        Route::get('/export', 'export')->middleware('can:admin.translations.index');
        Route::get('/export/all', 'exportAll')->middleware('can:admin.translations.index');
        Route::post('/import/preview', 'previewImport')->middleware('can:admin.translations.edit');
        Route::post('/import', 'import')->middleware('can:admin.translations.edit');
        Route::put('/{key}', 'update')->middleware('can:admin.translations.edit');
    });

Route::get('/app/translations', [TranslationController::class, 'getAppTranslations'])
    ->withoutMiddleware('auth');

Route::get('/app/employee-translations', [TranslationController::class, 'getEmployeeAppTranslations'])
    ->withoutMiddleware('auth');

Route::get('/app/driver-translations', [TranslationController::class, 'getDriverAppTranslations'])
    ->withoutMiddleware('auth');
