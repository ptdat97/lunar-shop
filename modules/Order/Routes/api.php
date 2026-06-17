<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\Api\V1\OrderController;

// Order API routes.
Route::prefix('api/v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
});