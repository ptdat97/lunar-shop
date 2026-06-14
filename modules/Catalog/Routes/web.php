<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Storefront\HomeController;

// Storefront (Blade) routes for the Catalog module.
Route::middleware('storefront')->group(function (): void {
    Route::get('/', HomeController::class)->name('storefront.home');
});
