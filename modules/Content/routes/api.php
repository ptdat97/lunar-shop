<?php

use Illuminate\Support\Facades\Route;
use Modules\Content\Http\Controllers\Api\V1\BannerController;
use Modules\Content\Http\Controllers\Api\V1\HomeFeedController;
use Modules\Content\Http\Controllers\Api\V1\PageController;

// CMS API routes.
Route::prefix('api/v1')->middleware('api')->group(function (): void {
    Route::get('pages', [PageController::class, 'index']);
    Route::get('pages/{slug}', [PageController::class, 'show']);
    Route::get('banners', [BannerController::class, 'index']);

    // The home page for headless clients — the section list the Blade home page
    // renders, as JSON.
    Route::get('home-feed', HomeFeedController::class);
});
