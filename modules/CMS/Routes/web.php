<?php

use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\Storefront\PageController;

// Storefront (Blade) routes for the CMS module.
Route::middleware('storefront')->group(function (): void {
    Route::get('pages/{slug}', [PageController::class, 'show'])->name('storefront.page');
});