<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\WebApplicationController;

Route::get('customer-display', [WebApplicationController::class, 'index']);
