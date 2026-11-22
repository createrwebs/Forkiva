<?php

use Modules\Report\Http\Controllers\Api\V1\ReportController;
use Modules\Report\Http\Controllers\Api\V1\ThaiAccountingExportController;

Route::post('reports/accounting-export', [ThaiAccountingExportController::class, 'export'])
    ->middleware('can:admin.reports.index');

Route::controller(ReportController::class)
    ->prefix('reports/{report}')
    ->middleware('can:admin.reports.index')
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/export/{method}', 'export');
    });
