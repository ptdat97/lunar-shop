<?php

use Illuminate\Support\Facades\Route;
use Acme\Recommend\Http\RecommendationController;

// Recommend API. Product recommendations are stateless (api); cart
// recommendations need the storefront session for the current cart.
Route::prefix('api/v1')->group(function (): void {
    Route::middleware('api')->group(function (): void {
        Route::get('products/{slug}/recommendations', [RecommendationController::class, 'forProduct'])
            ->name('api.v1.products.recommendations');
    });

    Route::middleware('storefront')->group(function (): void {
        Route::get('cart/recommendations', [RecommendationController::class, 'forCart'])
            ->name('api.v1.cart.recommendations');
    });
});
