<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Api\V1\StockNotificationController;

// API routes for the Inventory module. Prefixed with /api/v1 by ModulesServiceProvider.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    // Back-in-stock ("notify me") subscription.
    Route::post('inventory/notify-me', [StockNotificationController::class, 'store'])
        ->name('api.inventory.notify-me');
});
