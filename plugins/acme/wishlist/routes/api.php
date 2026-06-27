<?php

use Acme\Wishlist\Http\ApiWishlistController;
use Illuminate\Support\Facades\Route;

// Wishlist API — same routes/names/middleware as before the plugin split.

// Public index (guest-safe): the storefront loads it on every page to mark
// hearts, so it must NOT 401 for guests (returns an empty list).
Route::prefix('api/v1')->middleware('web')->group(function (): void {
    Route::get('wishlist', [ApiWishlistController::class, 'index'])->name('api.v1.wishlist.index');
});

// Toggle requires auth (Sanctum token or SPA cookie).
Route::prefix('api/v1')->middleware(['web', 'auth:sanctum'])->group(function (): void {
    Route::post('wishlist', [ApiWishlistController::class, 'toggle'])->name('api.v1.wishlist.toggle');
});
