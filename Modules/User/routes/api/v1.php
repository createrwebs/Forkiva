<?php

use Modules\User\Http\Controllers\Api\V1\AccountController;
use Modules\User\Http\Controllers\Api\V1\AuthController;
use Modules\User\Http\Controllers\Api\V1\CustomerController;
use Modules\User\Http\Controllers\Api\V1\CustomerPdpaController;
use Modules\User\Http\Controllers\Api\V1\PasskeyAuthController;
use Modules\User\Http\Controllers\Api\V1\PasskeyController;
use Modules\User\Http\Controllers\Api\V1\PasswordRecoveryController;
use Modules\User\Http\Controllers\Api\V1\RoleController;
use Modules\User\Http\Controllers\Api\V1\TwoFactorController;
use Modules\User\Http\Controllers\Api\V1\UserController;
use Modules\User\Http\Middleware\EnsurePasskeysEnabled;

Route::prefix('auth/password-recovery')
    ->controller(PasswordRecoveryController::class)
    ->withoutMiddleware('auth')
    ->group(function () {
        Route::get('options', 'options')->middleware('throttle:60,1,password-recovery-options');
        Route::post('identify', 'identify')->middleware('throttle:5,1,password-recovery-identify');
        Route::post('verify', 'verify')->middleware('throttle:10,1,password-recovery-verify');
        Route::post('code', 'requestCode')->middleware('throttle:5,1,password-recovery-code');
        Route::post('reset', 'reset')->middleware('throttle:10,1,password-recovery-reset');
    });

Route::controller(AuthController::class)
    ->prefix('auth')
    ->group(function () {
        Route::post('login', 'login')
            ->withoutMiddleware(middleware: 'auth')
            ->middleware('throttle:login');

        Route::post('two-factor/verify', 'verifyTwoFactor')
            ->withoutMiddleware(middleware: 'auth')
            ->middleware('throttle:two_factor');

        Route::post('logout', 'logout');
        Route::post('check', action: 'check');
    });

Route::controller(TwoFactorController::class)
    ->group(function () {
        Route::post('auth/two-factor/resend', 'resendLogin')
            ->withoutMiddleware(middleware: 'auth')
            ->middleware('throttle:two_factor');

        Route::prefix('accounts/two-factor')->group(function () {
            Route::get('/', 'status');
            Route::post('setup', 'setup');
            Route::post('setup/resend', 'resendSetup')->middleware('throttle:two_factor');
            Route::post('confirm', 'confirm');
            Route::post('code', 'sendCode')->middleware('throttle:two_factor');
            Route::delete('/', 'destroy');
            Route::post('recovery-codes', 'regenerateRecoveryCodes');
        });
    });

Route::controller(PasskeyAuthController::class)
    ->prefix('auth/passkeys')
    ->middleware(EnsurePasskeysEnabled::class)
    ->group(function () {
        Route::get('options', 'options')
            ->withoutMiddleware(middleware: 'auth')
            ->middleware('throttle:passkey_login');
        Route::post('login', 'login')
            ->withoutMiddleware(middleware: 'auth')
            ->middleware('throttle:passkey_login');
    });

Route::controller(PasskeyController::class)
    ->prefix('accounts/passkeys')
    ->middleware(EnsurePasskeysEnabled::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::post('options', 'options')->middleware('throttle:two_factor');
        Route::post('/', 'store');
        Route::delete('/{passkey}', 'destroy');
    });

Route::controller(RoleController::class)
    ->prefix('roles')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.roles.index');
        Route::get('/{id}', 'show')->middleware('permission:admin.roles.show|admin.roles.edit');
        Route::post('/', 'store')->middleware('can:admin.roles.create');
        Route::put('/{id}', 'update')->middleware('can:admin.roles.edit');
        Route::delete('/{ids}', 'destroy')->middleware('can:admin.roles.destroy');
        Route::get('/form/meta', 'getFormMeta')->middleware('permission:admin.roles.edit|admin.roles.create');
    });

Route::controller(UserController::class)
    ->prefix('users')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.users.index');
        Route::get('/{id}', 'show')->middleware('permission:admin.users.show|admin.users.edit');
        Route::post('/', 'store')->middleware('can:admin.users.create');
        Route::put('/{id}', 'update')->middleware('can:admin.users.edit');
        Route::delete('/{ids}', 'destroy')->middleware('can:admin.users.destroy');
        Route::get('/form/meta', 'getFormMeta')->middleware('permission:admin.users.edit|admin.users.create');
    });

Route::controller(CustomerController::class)
    ->prefix('customers')
    ->group(function () {
        Route::get('/', 'index')->middleware('can:admin.customers.index');
        Route::get('/{id}', 'show')->middleware('permission:admin.customers.show|admin.customers.edit');
        Route::post('/', 'store')->middleware('can:admin.customers.create');
        Route::put('/{id}', 'update')->middleware('can:admin.customers.edit');
        Route::delete('/{ids}', 'destroy')->middleware('can:admin.customers.destroy');
        Route::get('/form/meta', 'getFormMeta')->middleware('permission:admin.customers.edit|admin.customers.create');
    });

Route::controller(CustomerPdpaController::class)
    ->prefix('customers/{id}/pdpa')
    ->middleware('can:admin.customers.pdpa')
    ->group(function () {
        Route::get('export', 'export');
        Route::post('anonymize', 'anonymize');
    });

Route::controller(AccountController::class)
    ->prefix('accounts')
    ->group(function () {
        Route::get('me', 'me');
        Route::put('profile/update', 'updateProfile')->middleware('can:admin.profiles.edit');
        Route::put('password/update', 'updatePassword');
    });
