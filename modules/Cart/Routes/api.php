<?php

use Illuminate\Support\Facades\Route;
use Modules\Cart\Http\Controllers\Api\V1\CartController;

// Cart API. Lunar's CartSession is session-backed; the `storefront` group adds
// the Lunar storefront session (channel + customer groups) needed for pricing
// and discount matching. Same domain → session/Sanctum cookie auto-sent.
Route::prefix('api/v1')->middleware('storefront')->group(function (): void {
    Route::get('cart', [CartController::class, 'show'])->name('api.v1.cart.show');
    Route::post('cart', [CartController::class, 'store'])->name('api.v1.cart.store');
    Route::patch('cart/lines/{line}', [CartController::class, 'updateLine'])->name('api.v1.cart.lines.update');
    Route::delete('cart/lines/{line}', [CartController::class, 'destroyLine'])->name('api.v1.cart.lines.destroy');
    Route::post('cart/coupon/validate', [CartController::class, 'validateCoupon'])->name('api.v1.cart.coupon.validate');
    Route::post('cart/coupon', [CartController::class, 'applyCoupon'])->name('api.v1.cart.coupon.apply');
    Route::delete('cart/coupon', [CartController::class, 'removeCoupon'])->name('api.v1.cart.coupon.remove');
    Route::get('cart/coupons', [CartController::class, 'availableCoupons'])->name('api.v1.cart.coupons');
});
