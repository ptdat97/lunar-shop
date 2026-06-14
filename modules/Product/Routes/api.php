<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\Api\V1\ProductController;

// API routes for the Product module. Self-prefixed with /api/v1.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('products', [ProductController::class, 'index'])->name('api.v1.products.index');
    Route::get('products/{slug}', [ProductController::class, 'show'])->name('api.v1.products.show');
});
