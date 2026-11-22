<?php

use Modules\Dashboard\Enums\DashboardWidget;
use Modules\Dashboard\Http\Controllers\Api\V1\DashboardController;

$dashboardPermissions = collect(DashboardWidget::cases())
    ->map(fn (DashboardWidget $widget): string => $widget->permission())
    ->unique()
    ->implode('|');

Route::controller(DashboardController::class)
    ->prefix('dashboards')
    ->middleware('permission:'.$dashboardPermissions)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('filters', 'filters');
    });
