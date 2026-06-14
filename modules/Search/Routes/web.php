<?php

use Illuminate\Support\Facades\Route;
use Modules\Search\Http\Controllers\Storefront\SearchController;

// Storefront (Blade) routes for the Search module.
Route::middleware('storefront')->group(function (): void {
    Route::get('search', SearchController::class)->name('storefront.search');
});
