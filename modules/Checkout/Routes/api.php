<?php

use Illuminate\Support\Facades\Route;
use Modules\Checkout\Http\Controllers\Api\V1\CheckoutController;

// Checkout works on the session cart → stateful `web` group.
Route::prefix('api/v1')->middleware('storefront')->group(function (): void {
    Route::get('checkout/shipping-options', [CheckoutController::class, 'shippingOptions'])->name('api.v1.checkout.shipping-options');
    Route::post('checkout/addresses', [CheckoutController::class, 'addresses'])->name('api.v1.checkout.addresses');
    Route::post('checkout/shipping', [CheckoutController::class, 'shipping'])->name('api.v1.checkout.shipping');
    Route::post('checkout', [CheckoutController::class, 'place'])->name('api.v1.checkout.place');
});
