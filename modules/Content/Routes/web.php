<?php

use Illuminate\Support\Facades\Route;
use Modules\Content\Http\Controllers\Storefront\LookbookController;
use Modules\Content\Http\Controllers\Storefront\PageController;

// Storefront (Blade) routes for the CMS module.
Route::middleware('storefront')->group(function (): void {
    Route::get('pages/{slug}', [PageController::class, 'show'])->name('storefront.page');

    Route::get('lookbooks', [LookbookController::class, 'index'])->name('storefront.lookbooks');
    Route::get('lookbooks/{slug}', [LookbookController::class, 'show'])->name('storefront.lookbook');
});
