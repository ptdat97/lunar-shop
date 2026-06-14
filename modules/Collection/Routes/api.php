<?php

use Illuminate\Support\Facades\Route;
use Modules\Collection\Http\Controllers\Api\V1\CollectionController;

// API routes for the Collection module. Self-prefixed with /api/v1.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('collections/{slug}', [CollectionController::class, 'show'])->name('api.v1.collections.show');
});
