<?php

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\Api\V1\AuthController;
use Modules\Customer\Http\Controllers\Api\V1\CustomerController;
use Modules\Customer\Http\Controllers\Api\V1\WishlistController;

// Auth uses Sanctum SPA (cookie session) → stateful `web` group.
Route::prefix('api/v1')->middleware('web')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
});

// Authenticated customer endpoints. Accept either Sanctum token (app/headless)
// or the SPA cookie session.
Route::prefix('api/v1')->middleware(['web', 'auth:sanctum'])->group(function (): void {
    Route::get('customer', [CustomerController::class, 'show'])->name('api.v1.customer.show');
    Route::get('customer/orders', [CustomerController::class, 'orders'])->name('api.v1.customer.orders');

    Route::get('wishlist', [WishlistController::class, 'index'])->name('api.v1.wishlist.index');
    Route::post('wishlist', [WishlistController::class, 'toggle'])->name('api.v1.wishlist.toggle');
});
