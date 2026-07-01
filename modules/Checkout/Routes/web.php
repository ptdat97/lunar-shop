<?php

use Illuminate\Support\Facades\Route;
use Modules\Checkout\Http\Controllers\Storefront\CartController;
use Modules\Checkout\Http\Controllers\Storefront\CheckoutController;
use Modules\Checkout\Http\Controllers\VNPayController;

// Storefront (Blade) routes for the Checkout module (cart → checkout → payment).
Route::middleware('storefront')->group(function (): void {
    Route::get('cart', CartController::class)->name('storefront.cart');

    Route::get('checkout', [CheckoutController::class, 'index'])->name('storefront.checkout');
    Route::post('checkout', [CheckoutController::class, 'place'])->name('storefront.checkout.place');
    Route::get('checkout/confirmation/{reference}', [CheckoutController::class, 'confirmation'])->name('storefront.checkout.confirmation');

    // VNPay callbacks. `return` is the browser redirect (storefront session group);
    // `start` builds the redirect URL for the checkout island.
    Route::get('payment/vnpay/start/{order}', [VNPayController::class, 'start'])
        ->name('payment.vnpay.start');
    Route::get('payment/vnpay/return', [VNPayController::class, 'return'])
        ->name('payment.vnpay.return');
});

// IPN is called by VNPay servers — keep it outside session/CSRF middleware.
Route::get('payment/vnpay/ipn', [VNPayController::class, 'ipn'])
    ->name('payment.vnpay.ipn');
