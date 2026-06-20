<?php

use Illuminate\Support\Facades\Route;

// Storefront (Blade) routes for the Media module.
//
// NOTE: on-demand conversions are generated at URL-resolution time (see
// Modules\Media\Services\MediaUrl, used by API Resources + Blade), not via an
// HTTP route — that works in every environment (incl. `php artisan serve`,
// which serves the physical public/media directory and would shadow a route).
