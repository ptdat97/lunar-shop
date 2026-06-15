<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\Storefront\CartController;

// Storefront (Blade) routes for the Cart module.
Route::middleware('storefront')->group(function (): void {
    Route::get('cart', CartController::class)->name('storefront.cart');
});
