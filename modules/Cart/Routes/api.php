<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\Api\V1\CartController;

// Cart API. Lunar's CartSession is session-backed, so these run under the
// stateful `web` group (cookie session) — same domain as the storefront.
// Vue islands call them with the Sanctum/session cookie automatically.
Route::prefix('api/v1')->middleware('web')->group(function (): void {
    Route::get('cart', [CartController::class, 'show'])->name('api.v1.cart.show');
    Route::post('cart', [CartController::class, 'store'])->name('api.v1.cart.store');
    Route::patch('cart/lines/{line}', [CartController::class, 'updateLine'])->name('api.v1.cart.lines.update');
    Route::delete('cart/lines/{line}', [CartController::class, 'destroyLine'])->name('api.v1.cart.lines.destroy');
});
