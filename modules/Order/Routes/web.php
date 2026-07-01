<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\Storefront\InvoiceController;

// Storefront (Blade) routes for the Order module.

// Invoice PDF download — owner-only. Uses the `storefront` group (SPA cookie
// session) rather than `auth` (this app has no named login route); the
// controller returns 404 for guests / non-owners so nothing is revealed.
Route::middleware('storefront')->group(function (): void {
    Route::get('account/orders/{order}/invoice', InvoiceController::class)
        ->name('storefront.orders.invoice');
});
