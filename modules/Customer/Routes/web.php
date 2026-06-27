<?php

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\Storefront\AuthPageController;
use Modules\Customer\Http\Controllers\Storefront\WishlistController;

// Storefront (Blade) routes for the Customer module.
Route::middleware('storefront')->group(function (): void {
    Route::get('wishlist', WishlistController::class)->name('storefront.wishlist');

    Route::get('login', [AuthPageController::class, 'login'])->name('storefront.login');
    Route::get('register', [AuthPageController::class, 'register'])->name('storefront.register');
    Route::get('account', [AuthPageController::class, 'account'])->name('storefront.account');
});
