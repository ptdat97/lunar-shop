<?php

use Illuminate\Support\Facades\Route;
use Modules\Checkout\Http\Controllers\MoMoController;
use Modules\Checkout\Http\Controllers\Storefront\CartController;
use Modules\Checkout\Http\Controllers\Storefront\CheckoutController;
use Modules\Checkout\Http\Controllers\VNPayController;

// Storefront (Blade) routes for the Checkout module (cart → checkout → payment).
Route::middleware('storefront')->group(function (): void {
    Route::get('cart', CartController::class)->name('storefront.cart');

    Route::get('checkout', [CheckoutController::class, 'index'])->name('storefront.checkout');
    Route::post('checkout', [CheckoutController::class, 'place'])->name('storefront.checkout.place');
    Route::get('checkout/confirmation/{reference}', [CheckoutController::class, 'confirmation'])->name('storefront.checkout.confirmation');

    // VNPay callback: `return` is the browser redirect (storefront session group).
    Route::get('payment/vnpay/return', [VNPayController::class, 'return'])
        ->name('payment.vnpay.return');

    // MoMo return — browser redirect back after paying (query params).
    Route::get('payment/momo/return', [MoMoController::class, 'return'])
        ->name('payment.momo.return');
});

// IPN is called by the gateways' servers — keep it outside session/CSRF middleware.
Route::get('payment/vnpay/ipn', [VNPayController::class, 'ipn'])
    ->name('payment.vnpay.ipn');
Route::post('payment/momo/ipn', [MoMoController::class, 'ipn'])
    ->name('payment.momo.ipn');
