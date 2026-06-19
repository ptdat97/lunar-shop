<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\VNPayController;

// Storefront (Blade) routes for the Payment module.

// VNPay callbacks. `return` is the browser redirect (storefront session group);
// `ipn` is server-to-server from VNPay (no session/CSRF). `start` builds the
// redirect URL for the checkout island.
Route::middleware('storefront')->group(function (): void {
    Route::get('payment/vnpay/start/{order}', [VNPayController::class, 'start'])
        ->name('payment.vnpay.start');
    Route::get('payment/vnpay/return', [VNPayController::class, 'return'])
        ->name('payment.vnpay.return');
});

// IPN is called by VNPay servers — keep it outside session/CSRF middleware.
Route::get('payment/vnpay/ipn', [VNPayController::class, 'ipn'])
    ->name('payment.vnpay.ipn');
