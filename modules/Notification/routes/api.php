<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\Api\V1\DeviceController;
use Modules\Notification\Http\Controllers\Api\V1\NotificationController;

// In-app inbox + push registry. Both are per-user, so they sit behind the same
// guard as the rest of the customer surface: Sanctum token (app) or SPA cookie.
Route::prefix('api/v1')->middleware(['web', 'auth:sanctum', 'token.ability:customer:*'])->group(function (): void {
    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('api.v1.notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('api.v1.notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])
        ->name('api.v1.notifications.read');

    Route::post('devices', [DeviceController::class, 'store'])->name('api.v1.devices.store');
    Route::delete('devices', [DeviceController::class, 'destroy'])->name('api.v1.devices.destroy');
});
