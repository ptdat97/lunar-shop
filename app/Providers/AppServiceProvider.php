<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The Lunar admin panel is registered by ModulesServiceProvider after all
     * module providers have registered, so modules can contribute admin pages.
     * Shared infrastructure (settings, queues, admin pages) lives in the Core
     * module (Modules\Core), registered first by ModulesServiceProvider.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Rate limiters (backed by the cache store):
     *  - `api`  → applied to the whole `api` middleware group (bootstrap/app.php
     *    calls throttleApi()). Generous: storefront JS fires bursts (suggest
     *    autocomplete, facet filters, cart sync).
     *  - `auth` → brute-force guard on credential endpoints (login / register /
     *    token issue), applied per-route in the Customer module. Keyed by IP —
     *    these requests have no authenticated user yet.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
