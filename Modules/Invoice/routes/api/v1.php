<?php

use Modules\Invoice\Http\Controllers\Api\V1\InvoiceController;
use Modules\Invoice\Http\Controllers\Api\V1\ThaiTaxController;

Route::controller(ThaiTaxController::class)
    ->prefix('invoices')
    ->middleware('can:admin.invoices.index')
    ->group(function () {
        Route::post('/wht-calculate', 'calculateWht');
        Route::post('/etax-xml', 'buildETaxXml');
    });

Route::controller(InvoiceController::class)
    ->prefix('invoices')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.invoices.index');
        Route::get('/{reference}/show', 'show')->middleware('can:admin.invoices.show');
    });
