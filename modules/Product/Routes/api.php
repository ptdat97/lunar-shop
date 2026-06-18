<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\Api\V1\ProductController;
use Modules\Product\Http\Controllers\Api\V1\SizeController;

// API routes for the Product module. Self-prefixed with /api/v1.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('products', [ProductController::class, 'index'])->name('api.v1.products.index');
    Route::get('products/{slug}', [ProductController::class, 'show'])->name('api.v1.products.show');

    // Fashion Size Intelligence
    Route::get('products/{slug}/size-chart', [SizeController::class, 'chart'])->name('api.v1.products.size-chart');
    Route::post('products/{slug}/recommend-size', [SizeController::class, 'recommend'])->name('api.v1.products.recommend-size');
});
