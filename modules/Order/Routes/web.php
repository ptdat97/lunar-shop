<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\Storefront\InvoiceController;
use Modules\Order\Http\Controllers\Storefront\ReturnController;

// Storefront (Blade) routes for the Order module.

// Owner-only account order actions. Uses the `storefront` group (SPA cookie
// session) rather than `auth` (this app has no named login route); the
// controllers return 404 for guests / non-owners so nothing is revealed.
Route::middleware('storefront')->group(function (): void {
    Route::get('account/orders/{order}/invoice', InvoiceController::class)
        ->name('storefront.orders.invoice');

    // Return (RMA) request.
    Route::post('account/orders/{order}/returns', [ReturnController::class, 'store'])
        ->name('storefront.orders.returns.store');
});
