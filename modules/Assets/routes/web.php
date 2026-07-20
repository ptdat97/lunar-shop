<?php

use Illuminate\Support\Facades\Route;
use Modules\Assets\Http\Controllers\MediaConversionController;

// Storefront routes for the Assets module.
//
// Conversion fallback: existing files under public/media are served statically
// by the web server (Apache/nginx try_files, and `php artisan serve` alike), so
// this route only fires when a conversion FILE IS MISSING — the request falls
// through to Laravel, which generates that one size inline and streams it.
// This is what keeps images working before Horizon has drained the media queue
// (or with no worker running at all): the browser's own image requests generate
// the sizes, in parallel, one conversion per request.
//
// Middleware-free on purpose: an image response needs no session/CSRF/cookies,
// and skipping the web group keeps the generation hit as cheap as possible.
Route::get('media/{media}/conversions/{filename}', MediaConversionController::class)
    ->whereNumber('media')
    ->name('media.conversion');
