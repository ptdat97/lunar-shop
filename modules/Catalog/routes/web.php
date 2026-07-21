<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Storefront\CollectionController;
use Modules\Catalog\Http\Controllers\Storefront\HomeController;
use Modules\Catalog\Http\Controllers\Storefront\ProductController;
use Modules\Catalog\Http\Controllers\Storefront\SearchController;
use Modules\Catalog\Http\Controllers\Storefront\SitemapController;

// Storefront (Blade) routes for the Catalog module.
Route::middleware('storefront')->group(function (): void {
    Route::get('/', HomeController::class)->name('storefront.home');
    Route::get('products/{slug}', [ProductController::class, 'show'])->name('storefront.product');
    Route::get('collections/{slug}', [CollectionController::class, 'show'])->name('storefront.collection');
    Route::get('search', SearchController::class)->name('storefront.search');
});

// sitemap.xml — a machine endpoint (no storefront locale/session middleware).
Route::get('/sitemap.xml', SitemapController::class)->name('storefront.sitemap');
