<?php

use Illuminate\Support\Facades\Route;
use Modules\Checkout\Http\Controllers\Api\V1\CartController;
use Modules\Checkout\Http\Controllers\Api\V1\CheckoutController;
use Modules\Checkout\Http\Controllers\Api\V1\CouponController;
use Modules\Checkout\Http\Controllers\Api\V1\LoyaltyController;

// Cart + Checkout both work on the session cart → stateful `storefront` group.
// Lunar's CartSession is session-backed; the group adds the storefront session
// (channel + customer groups) needed for pricing and discount matching.
Route::prefix('api/v1')->middleware('storefront')->group(function (): void {
    // Cart
    Route::get('cart', [CartController::class, 'show'])->name('api.v1.cart.show');
    Route::post('cart', [CartController::class, 'store'])->name('api.v1.cart.store');
    Route::patch('cart/lines/{line}', [CartController::class, 'updateLine'])->name('api.v1.cart.lines.update');
    Route::delete('cart/lines/{line}', [CartController::class, 'destroyLine'])->name('api.v1.cart.lines.destroy');
    Route::post('cart/coupon/validate', [CouponController::class, 'validate'])->name('api.v1.cart.coupon.validate');
    Route::post('cart/coupon', [CouponController::class, 'apply'])->name('api.v1.cart.coupon.apply');
    Route::delete('cart/coupon', [CouponController::class, 'remove'])->name('api.v1.cart.coupon.remove');
    Route::get('cart/coupons', [CouponController::class, 'available'])->name('api.v1.cart.coupons');

    // Tiêu điểm thưởng. Cùng nhóm `storefront` với giỏ vì nó thao tác trên
    // đúng giỏ đó; quyền được kiểm bằng `cart.customer`, không phải middleware
    // — giỏ của khách vãng lai đơn giản là không có customer để mà tiêu.
    Route::post('cart/loyalty', [LoyaltyController::class, 'store'])->name('api.v1.cart.loyalty.store');
    Route::delete('cart/loyalty', [LoyaltyController::class, 'destroy'])->name('api.v1.cart.loyalty.destroy');

    // Checkout
    Route::get('checkout/shipping-options', [CheckoutController::class, 'shippingOptions'])->name('api.v1.checkout.shipping-options');
    Route::post('checkout/addresses', [CheckoutController::class, 'addresses'])->name('api.v1.checkout.addresses');
    Route::post('checkout/pickup', [CheckoutController::class, 'pickup'])->name('api.v1.checkout.pickup');
    Route::post('checkout/shipping', [CheckoutController::class, 'shipping'])->name('api.v1.checkout.shipping');
    Route::post('checkout', [CheckoutController::class, 'place'])->name('api.v1.checkout.place');
});
