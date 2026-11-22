<?php

use Modules\Payment\Http\Controllers\Api\V1\PaymentController;
use Modules\Payment\Http\Controllers\Api\V1\SlipVerificationController;

Route::controller(PaymentController::class)
    ->prefix('payments')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.payments.index');
        Route::get('/{id}', 'show')->middleware('can:admin.payments.show')->whereNumber('id');
    });

Route::controller(SlipVerificationController::class)
    ->prefix('payments')
    ->group(function () {
        Route::post('/slip-verify/parse', 'parse')->middleware('can:admin.orders.receive_payment');
        Route::post('/slip-verify', 'verify')->middleware('can:admin.orders.receive_payment');
        Route::get('/slip-verifications', 'index')->middleware('can:admin.payments.index');
    });
