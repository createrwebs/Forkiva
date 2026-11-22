<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V1\TenantController;

Route::post('public/register-tenant', [TenantController::class, 'register'])
    ->withoutMiddleware('auth')
    ->name('public.tenants.register');

Route::post('webhooks/subscription-billing', [TenantController::class, 'billingWebhook'])
    ->withoutMiddleware('auth')
    ->name('webhooks.subscription.billing');

Route::prefix('tenant')
    ->middleware('tenant')
    ->group(function (): void {
        Route::get('current', [TenantController::class, 'current'])->name('tenant.current');
        Route::post('onboarding/complete', [TenantController::class, 'completeOnboarding'])->name('tenant.onboarding.complete');
    });
