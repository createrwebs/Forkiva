<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservation\Http\Controllers\Api\V1\ReservationAnalyticsController;
use Modules\Reservation\Http\Controllers\Api\V1\ReservationController;
use Modules\Reservation\Http\Controllers\Api\V1\ReservationDepositController;
use Modules\Reservation\Http\Controllers\Api\V1\ReservationSettingController;
use Modules\Reservation\Http\Controllers\Api\V1\ReservationTableBlockController;
use Modules\Reservation\Http\Controllers\Api\V1\ReservationWaitlistController;

Route::controller(ReservationController::class)
    ->prefix('reservations')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.reservations.index');
        Route::get('/form/meta', 'getFormMeta')->middleware('permission:admin.reservations.create|admin.reservations.edit');
        Route::post('/availability', 'availability')->middleware('can:admin.reservations.view_availability');
        Route::post('/suggested-tables', 'suggestedTables')->middleware('can:admin.reservations.view_availability');
        Route::post('/suggested-slots', 'suggestedSlots')->middleware('can:admin.reservations.view_availability');
        Route::get('/calendar', 'calendar')->middleware('can:admin.reservations.index');
        Route::get('/unassigned', 'unassigned')->middleware('can:admin.reservations.assign_table');
        Route::get('/{id}', 'show')->middleware('permission:admin.reservations.show|admin.reservations.edit');
        Route::post('/', 'store')->middleware('can:admin.reservations.create');
        Route::put('/{id}', 'update')->middleware('can:admin.reservations.edit');
        Route::delete('/{ids}', 'destroy')->middleware('can:admin.reservations.destroy');
        Route::patch('/{id}/status', 'changeStatus')->middleware('permission:admin.reservations.confirm|admin.reservations.reject|admin.reservations.cancel|admin.reservations.mark_arrived|admin.reservations.seat|admin.reservations.complete|admin.reservations.mark_no_show');
        Route::put('/{id}/tables', 'assignTables')->middleware('can:admin.reservations.assign_table');
        Route::post('/{id}/tables/auto-assign', 'autoAssignTables')->middleware('can:admin.reservations.auto_assign_table');
        Route::delete('/{id}/tables/{tableId}', 'removeTable')->middleware('can:admin.reservations.reassign_table');
    });

Route::controller(ReservationSettingController::class)
    ->prefix('reservation-settings')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.reservation_settings.index');
        Route::get('/form/meta', 'getFormMeta')->middleware('permission:admin.reservation_settings.create|admin.reservation_settings.edit');
        Route::get('/{id}', 'show')->middleware('permission:admin.reservation_settings.show|admin.reservation_settings.edit');
        Route::post('/', 'store')->middleware('can:admin.reservation_settings.create');
        Route::put('/{id}', 'update')->middleware('can:admin.reservation_settings.edit');
        Route::delete('/{ids}', 'destroy')->middleware('can:admin.reservation_settings.destroy');
    });

Route::controller(ReservationDepositController::class)
    ->prefix('reservation-deposits')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.reservation_deposit_transactions.index');
        Route::get('/reservation/{reservationId}/form/meta', 'getFormMeta')
            ->middleware('permission:admin.reservation_deposit_transactions.create|admin.reservation_deposit_transactions.edit');
        Route::get('/reservation/{reservationId}/eligible-orders', 'searchEligibleOrders')
            ->middleware('permission:admin.reservation_deposit_transactions.create|admin.reservation_deposit_transactions.edit');
        Route::get('/{transactionId}', 'show')->middleware('can:admin.reservation_deposit_transactions.show');
        Route::post('/reservation/{reservationId}', 'store')->middleware('can:admin.reservation_deposit_transactions.create');
        Route::post('/{transactionId}/reverse', 'reverse')->middleware('can:admin.reservation_deposit_transactions.edit');
    });

Route::controller(ReservationWaitlistController::class)
    ->prefix('reservation-waitlists')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.reservation_waitlists.index');
        Route::get('/form/meta', 'getFormMeta')->middleware('permission:admin.reservation_waitlists.create|admin.reservation_waitlists.edit');
        Route::get('/{id}', 'show')->middleware('permission:admin.reservation_waitlists.show|admin.reservation_waitlists.edit');
        Route::post('/', 'store')->middleware('can:admin.reservation_waitlists.create');
        Route::put('/{id}', 'update')->middleware('can:admin.reservation_waitlists.edit');
        Route::delete('/{ids}', 'destroy')->middleware('can:admin.reservation_waitlists.destroy');
        Route::post('/{id}/notify', 'notify')->middleware('can:admin.reservation_waitlists.notify');
        Route::post('/{id}/convert', 'convert')->middleware('can:admin.reservation_waitlists.convert_to_reservation');
        Route::post('/{id}/cancel', 'cancel')->middleware('can:admin.reservation_waitlists.cancel');
    });

Route::controller(ReservationTableBlockController::class)
    ->prefix('reservation-table-blocks')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.reservation_table_blocks.index');
        Route::get('/{id}', 'show')->middleware('permission:admin.reservation_table_blocks.show|admin.reservation_table_blocks.edit');
        Route::post('/', 'store')->middleware('can:admin.reservation_table_blocks.create');
        Route::put('/{id}', 'update')->middleware('can:admin.reservation_table_blocks.edit');
        Route::delete('/{ids}', 'destroy')->middleware('can:admin.reservation_table_blocks.destroy');
    });

Route::controller(ReservationAnalyticsController::class)
    ->prefix('reservation-analytics')
    ->middleware('can:admin.reservations.analytics')
    ->group(function () {
        Route::get('/filters', 'filters');
        Route::get('/', 'index');
    });
