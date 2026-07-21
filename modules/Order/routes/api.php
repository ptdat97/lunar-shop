<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\Api\V1\OrderController;

// Order API routes. `web` is needed so the Sanctum SPA cookie session is read
// (storefront account uses cookie auth, not bearer tokens).
//
// `token.ability:customer:*` scopes bearer tokens to the customer surface, the
// same guard the account endpoints carry — a POS token must not read a
// customer's order history. Cookie sessions pass through untouched.
Route::prefix('api/v1')->middleware(['web', 'auth:sanctum', 'token.ability:customer:*'])->group(function (): void {
    Route::get('orders', [OrderController::class, 'index'])->name('api.v1.orders.index');
    Route::get('orders/{id}', [OrderController::class, 'show'])->name('api.v1.orders.show');

    // Status history, read from Lunar's activity log (no timeline table).
    Route::get('orders/{id}/timeline', [OrderController::class, 'timeline'])
        ->name('api.v1.orders.timeline');
});
