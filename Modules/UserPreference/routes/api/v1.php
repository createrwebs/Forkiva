<?php

use Modules\UserPreference\Http\Controllers\Api\V1\UserPreferenceController;

Route::controller(UserPreferenceController::class)
    ->prefix('preferences')
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{identifier}', 'show')->where('identifier', 'ui\.[a-z0-9]+(?:[._:-][a-z0-9]+)*');
        Route::put('/{identifier}', 'update')->where('identifier', 'ui\.[a-z0-9]+(?:[._:-][a-z0-9]+)*');
        Route::delete('/{identifier}', 'destroy')->where('identifier', 'ui\.[a-z0-9]+(?:[._:-][a-z0-9]+)*');
    });
