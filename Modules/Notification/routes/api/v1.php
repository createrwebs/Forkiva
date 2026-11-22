<?php

use Modules\Notification\Http\Controllers\Api\V1\NotificationController;
use Modules\Notification\Http\Controllers\Api\V1\NotificationPushTokenController;
use Modules\Notification\Http\Controllers\Api\V1\NotificationTemplateController;
use Modules\Notification\Http\Controllers\Api\V1\NotificationWorkflowController;

Route::controller(NotificationController::class)
    ->prefix('notifications')
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/summary', 'summary');
        Route::get('/{notificationId}', 'show');
        Route::patch('/read-all', 'readAll');
        Route::patch('/{notificationId}/read', 'read');
    });

Route::controller(NotificationWorkflowController::class)
    ->prefix('notification-workflows')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.notification_workflows.index');
        Route::get('/{key}', 'show')->middleware('permission:admin.notification_workflows.index|admin.notification_workflows.edit');
        Route::put('/{key}', 'update')->middleware('can:admin.notification_workflows.edit');
    });

Route::controller(NotificationTemplateController::class)
    ->prefix('notification-templates')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.notification_templates.index');
        Route::get('/{key}', 'show')->middleware('permission:admin.notification_templates.index|admin.notification_templates.show');
        Route::put('/{key}', 'update')->middleware('can:admin.notification_templates.edit');
    });

Route::controller(NotificationPushTokenController::class)
    ->prefix('notification-push-tokens')
    ->group(function () {
        Route::post('/', 'store');
        Route::delete('/{id}', 'destroy');
    });
