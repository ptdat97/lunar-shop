<?php

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\Api\V1\AddressController;
use Modules\Customer\Http\Controllers\Api\V1\AuthController;
use Modules\Customer\Http\Controllers\Api\V1\CustomerController;
use Modules\Customer\Http\Controllers\Api\V1\WishlistController;

// Auth uses Sanctum SPA (cookie session) → stateful `web` group.
Route::prefix('api/v1')->middleware('web')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

    // Login-state probes fired on page load by the storefront (header wishlist
    // heart, account bootstrap). These must NOT 401 for guests — that surfaces
    // as a red console error in the browser. They return a guest payload (null /
    // empty) when no user is authenticated; the controllers handle that.
    Route::get('customer', [CustomerController::class, 'show'])->name('api.v1.customer.show');
    Route::get('wishlist', [WishlistController::class, 'index'])->name('api.v1.wishlist.index');
});

// Authenticated customer endpoints. Accept either Sanctum token (app/headless)
// or the SPA cookie session.
Route::prefix('api/v1')->middleware(['web', 'auth:sanctum'])->group(function (): void {
    Route::patch('customer', [CustomerController::class, 'update'])->name('api.v1.customer.update');
    Route::patch('customer/password', [CustomerController::class, 'password'])->name('api.v1.customer.password');
    Route::get('customer/orders', [CustomerController::class, 'orders'])->name('api.v1.customer.orders');

    // Address book (CRUD).
    Route::get('customer/addresses', [AddressController::class, 'index'])->name('api.v1.customer.addresses.index');
    Route::post('customer/addresses', [AddressController::class, 'store'])->name('api.v1.customer.addresses.store');
    Route::patch('customer/addresses/{address}', [AddressController::class, 'update'])->name('api.v1.customer.addresses.update');
    Route::delete('customer/addresses/{address}', [AddressController::class, 'destroy'])->name('api.v1.customer.addresses.destroy');

    Route::post('wishlist', [WishlistController::class, 'toggle'])->name('api.v1.wishlist.toggle');
});
