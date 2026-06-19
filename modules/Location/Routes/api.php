<?php

use Illuminate\Support\Facades\Route;
use Modules\Location\Http\Controllers\Api\V1\LocationController;

// Public location lookups (no auth/session needed) for address dropdowns.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('locations/provinces', [LocationController::class, 'provinces'])
        ->name('api.v1.locations.provinces');
    Route::get('locations/provinces/{province}/wards', [LocationController::class, 'wards'])
        ->name('api.v1.locations.wards');
});
