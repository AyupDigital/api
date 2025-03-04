<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

Route::prefix('/kiosk/v1')
    ->group(
        function () {
            Route::post('/events', [\Modules\Kiosk\App\Http\Controllers\KioskEventController::class, 'store']);
            Route::post('/notifications/sms', [\Modules\Kiosk\App\Http\Controllers\KioskNotification\SmsController::class, '__invoke']);
            Route::post('/notifications/email', [\Modules\Kiosk\App\Http\Controllers\KioskNotification\EmailController::class, '__invoke']);
            Route::post('/notifications/letter', [\Modules\Kiosk\App\Http\Controllers\KioskNotification\LetterController::class, '__invoke']);
            Route::post('/notifications/print', [\Modules\Kiosk\App\Http\Controllers\KioskNotification\PrintController::class, '__invoke']);
            Route::get('/notifications/print', [\Modules\Kiosk\App\Http\Controllers\KioskNotification\PrintController::class, '__invoke']);
        }
    );
