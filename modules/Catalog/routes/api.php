<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\CollectionController;
use Modules\Catalog\Http\Controllers\Api\V1\HealthController;
use Modules\Catalog\Http\Controllers\Api\V1\ProductController;
use Modules\Catalog\Http\Controllers\Api\V1\RecommendationController;
use Modules\Catalog\Http\Controllers\Api\V1\ReviewController;
use Modules\Catalog\Http\Controllers\Api\V1\SearchController;
use Modules\Catalog\Http\Controllers\Api\V1\SizeController;

// Health probe: no middleware. The `api` group resolves the storefront locale
// through a cached settings store, so a Redis outage would 500 this endpoint
// before it could report which dependency is down. A probe must depend on as
// little as possible — it is the thing that tells you the rest is broken.
Route::get('api/v1/health', HealthController::class)->name('api.v1.health');

// API routes for the Catalog module. Self-prefixed with /api/v1.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
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

// Reviews. Previously registered on the bare `api/v1` prefix with no middleware
// group at all, so they resolved no API locale — reviews came back in the store
// default whatever the client asked for.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('products/{product}/reviews', [ReviewController::class, 'index'])
        ->name('api.v1.products.reviews.index');
    Route::post('products/{product}/reviews', [ReviewController::class, 'store'])
        ->name('api.v1.products.reviews.store');
});

// Cart recommendations need the storefront session for the current cart.
Route::prefix('api/v1')->middleware('storefront')->group(function (): void {
    Route::get('cart/recommendations', [RecommendationController::class, 'forCart'])
        ->name('api.v1.cart.recommendations');
});
