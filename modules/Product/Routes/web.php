<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\Storefront\ProductController;

// Storefront (Blade) routes for the Product module.
Route::middleware('storefront')->group(function (): void {
    Route::get('products/{slug}', [ProductController::class, 'show'])->name('storefront.product');
});
