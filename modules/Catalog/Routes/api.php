<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\CollectionController;
use Modules\Catalog\Http\Controllers\Api\V1\HealthController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductController;
use Modules\Catalog\Http\Controllers\Api\V1\RecommendationController;
use Modules\Catalog\Http\Controllers\Api\V1\ReviewController;
use Modules\Catalog\Http\Controllers\Api\V1\SearchController;
use Modules\Catalog\Http\Controllers\Api\V1\SizeController;

// API routes for the Catalog module. Self-prefixed with /api/v1.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('health', HealthController::class)->name('api.v1.health');

    // Products
    Route::get('products', [ProductController::class, 'index'])->name('api.v1.products.index');
    Route::get('products/{slug}', [ProductController::class, 'show'])->name('api.v1.products.show');

    // Fashion Size Intelligence
    Route::get('products/{slug}/size-chart', [SizeController::class, 'chart'])->name('api.v1.products.size-chart');
    Route::post('products/{slug}/recommend-size', [SizeController::class, 'recommend'])->name('api.v1.products.recommend-size');

    // Collections
    Route::get('collections/{slug}', [CollectionController::class, 'show'])->name('api.v1.collections.show');

    // Search
    Route::get('search', [SearchController::class, 'index'])->name('api.v1.search');
    Route::get('search/suggest', [SearchController::class, 'suggest'])->name('api.v1.search.suggest');

    // Product recommendations (stateless).
    Route::get('products/{slug}/recommendations', [RecommendationController::class, 'forProduct'])
        ->name('api.v1.products.recommendations');
});

// Reviews — no `api` middleware group in the original (public read/write on the
// bare api/v1 prefix); preserved as-is.
Route::prefix('api/v1')->group(function (): void {
    Route::get('products/{product}/reviews', [ReviewController::class, 'index'])
        ->name('api.v1.products.reviews.index');
    Route::post('products/{product}/reviews', [ReviewController::class, 'store'])
        ->name('api.v1.products.reviews.store');

    // Cart recommendations need the storefront session for the current cart.
    Route::middleware('storefront')->group(function (): void {
        Route::get('cart/recommendations', [RecommendationController::class, 'forCart'])
            ->name('api.v1.cart.recommendations');
    });
});
