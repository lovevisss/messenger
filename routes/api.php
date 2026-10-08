<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Middleware\AuthenticateClientSystem;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware([AuthenticateClientSystem::class, 'throttle:notification-clients'])->group(function () {
    Route::post('notifications', [NotificationController::class, 'store']);
    Route::get('notifications/{notification}', [NotificationController::class, 'show']);
    Route::post('portal-tasks/{businessTaskId}/{action}', [NotificationController::class, 'changePortal']);
});
