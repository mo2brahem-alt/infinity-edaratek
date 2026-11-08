<?php

use App\Http\Controllers\Mobile\NativeSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [NativeSessionController::class, 'login']);

    Route::middleware(['native.bearer', 'native.throttle:session'])->group(function (): void {
        // Logout remains available for a valid current token after an account
        // becomes blocked, so the device can always remove that credential.
        Route::post('/auth/logout', [NativeSessionController::class, 'logout']);
    });

    Route::middleware(['native.bearer', 'native.throttle:read', 'native.account'])->group(function (): void {
        Route::get('/me', [NativeSessionController::class, 'me']);
        Route::get('/auth/devices', [NativeSessionController::class, 'devices']);
    });

    Route::middleware(['native.bearer', 'native.throttle:session', 'native.account'])->group(function (): void {
        Route::delete('/auth/devices/{token}', [NativeSessionController::class, 'destroyDevice']);
        Route::delete('/auth/devices', [NativeSessionController::class, 'destroyAllDevices']);
    });
});
