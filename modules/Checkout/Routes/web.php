<?php

use Illuminate\Support\Facades\Route;
use Modules\Checkout\Http\Controllers\Storefront\CheckoutController;

// Storefront (Blade) routes for the Checkout module.
Route::middleware('storefront')->group(function (): void {
    Route::get('checkout', [CheckoutController::class, 'index'])->name('storefront.checkout');
    Route::get('checkout/confirmation/{reference}', [CheckoutController::class, 'confirmation'])->name('storefront.checkout.confirmation');
});
