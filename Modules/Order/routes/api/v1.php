<?php

use Modules\Order\Http\Controllers\Api\V1\OrderController;
use Modules\Order\Http\Controllers\Api\V1\ReasonController;
use Modules\Order\Http\Controllers\Api\V1\ThaiOrderToolsController;
use Modules\Printer\Enum\PrintContentType;

Route::controller(OrderController::class)
    ->prefix('orders')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.orders.index');
        Route::get('/{orderId}/show', 'show')->middleware('can:admin.orders.show');
        Route::prefix('/{orderId}/print')
            ->middleware('permission:admin.orders.print|admin.orders.create|admin.orders.receive_payment')
            ->group(function () {
                Route::get('/', 'printMeta');
                Route::get('{type}/preview', 'previewPrint')
                    ->whereIn('type', PrintContentType::values());
            });
        Route::get('/{orderId}/edit', 'edit')->middleware('can:admin.orders.edit')->whereNumber('orderId');
        Route::post('/quote', 'quote')->middleware('can:admin.orders.create');
        Route::post('/{orderId}/quote', 'quote')->middleware('can:admin.orders.edit')->whereNumber('orderId');
        Route::post('/', 'store')->middleware('can:admin.orders.create');
        Route::put('/{orderId}', 'update')->middleware('can:admin.orders.edit')->whereNumber('orderId');
        Route::post('/{orderId}/cancel', 'cancel')->middleware('can:admin.orders.cancel');
        Route::post('/{orderId}/refund', 'refund')->middleware('can:admin.orders.refund');
        Route::get('/{orderId}/update-status/meta', 'getUpdateStatusMeta')
            ->middleware('permission:admin.orders.cancel|admin.orders.refund');
        Route::get('active', 'activeOrders')
            ->middleware('can:admin.orders.active');
        Route::get('upcoming', 'upcomingOrders')
            ->middleware('can:admin.orders.upcoming');

        Route::prefix('{orderId}/payments')
            ->middleware('can:admin.orders.receive_payment')
            ->group(function () {
                Route::post('', 'storePayment');
                Route::get('meta', 'getPaymentMeta');
                Route::get('promptpay-qr', 'promptPayQr');
            });

        Route::patch('/{orderId}/move-to-next-status', 'moveToNextStatus')->middleware('can:admin.orders.update_status');
    });

Route::controller(ReasonController::class)
    ->prefix('reasons')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.reasons.index');
        Route::get('/{id}', 'show')->middleware('permission:admin.reasons.show|admin.reasons.edit');
        Route::post('/', 'store')->middleware('can:admin.reasons.create');
        Route::put('/{id}', 'update')->middleware('can:admin.reasons.edit');
        Route::delete('/{ids}', 'destroy')->middleware('can:admin.reasons.destroy');
        Route::get('/form/meta', 'getFormMeta')->middleware('permission:admin.reasons.edit|admin.reasons.create');
    });

Route::controller(ThaiOrderToolsController::class)
    ->group(function () {
        Route::post('orders/split-equal-preview', 'splitEqualPreview')->middleware('can:admin.orders.receive_payment');
        Route::post('orders/channel-gp-preview', 'channelGpPreview')->middleware('can:admin.orders.index');
        Route::post('webhooks/delivery/{provider}', 'deliveryWebhook')->withoutMiddleware('auth');
    });
