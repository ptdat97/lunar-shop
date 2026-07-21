<?php

use Illuminate\Support\Facades\Route;
use Modules\Theme\Http\Controllers\Storefront\LocaleController;

// Storefront (Blade) routes for the Theme module.
Route::middleware('storefront')->group(function (): void {
    // Language switch — persists the locale to the session, redirects back.
    Route::get('locale/{locale}', [LocaleController::class, 'switch'])
        ->name('storefront.locale.switch');
});
