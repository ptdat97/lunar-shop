<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\Api\V1\PromotionController;

// API routes for the Promotion module. Prefixed with /api/v1 by ModulesServiceProvider.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    // Public: active automatic promotions + current flash sale (banners/badges).
    Route::get('promotions', [PromotionController::class, 'index'])
        ->name('api.v1.promotions.index');
});

// Authenticated: the customer's loyalty/membership tier + progress.
Route::prefix('api/v1')->middleware(['web', 'auth:sanctum'])->group(function (): void {
    Route::get('promotions/membership', [PromotionController::class, 'membership'])
        ->name('api.v1.promotions.membership');
});
