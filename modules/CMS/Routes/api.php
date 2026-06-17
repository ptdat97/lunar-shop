<?php

use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\Api\V1\BannerController;
use Modules\CMS\Http\Controllers\Api\V1\PageController;

// CMS API routes.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('pages', [PageController::class, 'index']);
    Route::get('pages/{slug}', [PageController::class, 'show']);
    Route::get('banners', [BannerController::class, 'index']);
});