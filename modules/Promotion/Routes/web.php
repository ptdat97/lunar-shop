<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\Storefront\PromotionPageController;

// Storefront (Blade) routes for the Promotion module.
Route::middleware('storefront')->group(function (): void {
    Route::get('promotions', [PromotionPageController::class, 'index'])
        ->name('storefront.promotions');
    Route::get('promotions/{handle}', [PromotionPageController::class, 'show'])
        ->name('storefront.promotion');
});
