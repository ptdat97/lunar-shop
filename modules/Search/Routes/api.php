<?php

use Illuminate\Support\Facades\Route;
use Modules\Search\Http\Controllers\Api\V1\SearchController;

// API routes for the Search module. Self-prefixed with /api/v1.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('search', [SearchController::class, 'index'])->name('api.v1.search');
    Route::get('search/suggest', [SearchController::class, 'suggest'])->name('api.v1.search.suggest');
});
