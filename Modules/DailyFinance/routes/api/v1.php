<?php

use Modules\DailyFinance\Http\Controllers\Api\V1\CashHandoverController;
use Modules\DailyFinance\Http\Controllers\Api\V1\DailyFinanceController;
use Modules\DailyFinance\Http\Controllers\Api\V1\DayClosingController;
use Modules\DailyFinance\Http\Controllers\Api\V1\PaymentSettlementController;

Route::middleware('permission:admin.daily_finance.index')->group(function () {
    Route::prefix('daily-finance/settlements')
        ->middleware([
            'permission:admin.daily_finance_settlements.index',
            'permission:admin.payments.index',
        ])
        ->group(function () {
            Route::get('meta', [PaymentSettlementController::class, 'meta']);
            Route::get('summary', [PaymentSettlementController::class, 'summary']);
            Route::get('candidates', [PaymentSettlementController::class, 'candidates']);
            Route::get('', [PaymentSettlementController::class, 'index']);
            Route::get('{settlement}', [PaymentSettlementController::class, 'show'])->whereNumber('settlement');
            Route::post('', [PaymentSettlementController::class, 'store'])->middleware('permission:admin.daily_finance_settlements.create');
            Route::post('{settlement}/receive', [PaymentSettlementController::class, 'receive'])->whereNumber('settlement')->middleware('permission:admin.daily_finance_settlements.receive');
            Route::post('{settlement}/cancel', [PaymentSettlementController::class, 'cancel'])->whereNumber('settlement')->middleware('permission:admin.daily_finance_settlements.cancel');
        });
    Route::prefix('daily-finance/closings')
        ->middleware([
            'permission:admin.daily_finance_closings.index',
            'permission:admin.orders.index',
            'permission:admin.payments.index',
            'permission:admin.pos_cash_movements.index',
            'permission:admin.pos_sessions.index',
            'permission:admin.pos_sessions.show',
            'permission:admin.daily_finance_handovers.index',
        ])->group(function () {
            Route::get('review', [DayClosingController::class, 'review']);
            Route::get('', [DayClosingController::class, 'index']);
            Route::post('', [DayClosingController::class, 'store'])->middleware('permission:admin.daily_finance_closings.close');
        });

    Route::prefix('daily-finance/handovers')
        ->middleware([
            'permission:admin.pos_sessions.index',
            'permission:admin.pos_sessions.show',
            'permission:admin.daily_finance_handovers.index',
        ])->group(function () {
            Route::get('summary', [CashHandoverController::class, 'followUp']);
            Route::get('sessions', [CashHandoverController::class, 'followUpSessions']);
        });

    Route::get('daily-finance/entries', [DailyFinanceController::class, 'entries']);
    Route::prefix('daily-finance/sessions/{session}/handovers')
        ->whereNumber('session')
        ->middleware([
            'permission:admin.pos_sessions.show',
            'permission:admin.daily_finance_handovers.index',
        ])
        ->group(function () {
            Route::get('', [CashHandoverController::class, 'index']);
            Route::post('', [CashHandoverController::class, 'store'])
                ->middleware('permission:admin.daily_finance_handovers.create');
            Route::post('{handover}/receive', [CashHandoverController::class, 'receive'])
                ->middleware('permission:admin.daily_finance_handovers.receive')
                ->whereNumber('handover');
            Route::post('{handover}/cancel', [CashHandoverController::class, 'cancel'])
                ->middleware('permission:admin.daily_finance_handovers.cancel')
                ->whereNumber('handover');
        });

    Route::get('daily-finance', [DailyFinanceController::class, 'index']);
});
