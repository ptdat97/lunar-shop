<?php

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\Storefront\AuthPageController;

// Storefront (Blade) routes for the Customer module.
// (Wishlist routes moved to the acme/wishlist plugin.)
Route::middleware('storefront')->group(function (): void {
    Route::get('login', [AuthPageController::class, 'login'])->name('storefront.login');
    Route::get('register', [AuthPageController::class, 'register'])->name('storefront.register');
    Route::get('account', [AuthPageController::class, 'account'])->name('storefront.account');
});
