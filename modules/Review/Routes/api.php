<?php

use Modules\Review\Http\Controllers\Api\V1\ReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->group(function (): void {
    Route::get('products/{product}/reviews', [ReviewController::class, 'index'])
        ->name('api.v1.products.reviews.index');
    Route::post('products/{product}/reviews', [ReviewController::class, 'store'])
        ->name('api.v1.products.reviews.store');
});
