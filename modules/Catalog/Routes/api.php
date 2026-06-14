<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\HealthController;

// API routes for the Catalog module. Self-prefixed with /api/v1.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('health', HealthController::class)->name('api.v1.health');
});
