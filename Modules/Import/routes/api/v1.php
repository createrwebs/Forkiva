<?php

use Modules\Import\Http\Controllers\Api\V1\ImportController;

Route::controller(ImportController::class)
    ->prefix('imports')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{resource}/fields', 'fields')->name('fields');
        Route::get('/{resource}/template', 'template')->name('template');
        Route::post('/{resource}', 'upload')->name('upload');
        Route::get('/{id}', 'show')->name('show');
        Route::get('/{id}/rows', 'rows')->name('rows');
        Route::post('/{id}/mapping', 'map')->name('map');
        Route::patch('/{id}/rows/{rowId}', 'editRow')->name('edit-row');
        Route::post('/{id}/validate', 'validateImport')->name('validate');
        Route::post('/{id}/queue', 'queue')->name('queue');
        Route::post('/{id}/advance', 'advance')
            ->withoutMiddleware('throttle:api')->middleware('throttle:import-batches')->name('advance');
        Route::post('/{id}/retry', 'retry')->name('retry');
        Route::post('/{id}/cancel', 'cancel')->name('cancel');
        Route::get('/{id}/download', 'download')->name('download');
        Route::get('/{id}/errors', 'errors')->name('errors');
    });
