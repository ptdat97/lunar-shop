<?php

use Acme\Wishlist\Http\StorefrontWishlistController;
use Illuminate\Support\Facades\Route;

// Wishlist storefront page — same route/name/middleware as before the plugin split.
Route::middleware('storefront')->group(function (): void {
    Route::get('wishlist', StorefrontWishlistController::class)->name('storefront.wishlist');
});
