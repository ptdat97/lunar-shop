<?php

use Illuminate\Support\Facades\Route;
use Modules\Collection\Http\Controllers\Storefront\CollectionController;

// Storefront (Blade) routes for the Collection module.
Route::middleware('storefront')->group(function (): void {
    Route::get('collections/{slug}', [CollectionController::class, 'show'])->name('storefront.collection');
});
