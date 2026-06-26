<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Storefront\HomeController;
use Modules\Catalog\Http\Controllers\Storefront\SitemapController;

// Storefront (Blade) routes for the Catalog module.
Route::middleware('storefront')->group(function (): void {
    Route::get('/', HomeController::class)->name('storefront.home');
});

// sitemap.xml — a machine endpoint (no storefront locale/session middleware).
Route::get('/sitemap.xml', SitemapController::class)->name('storefront.sitemap');
