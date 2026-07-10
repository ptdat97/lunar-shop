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
     *  - `api`  → baseline for every `api/v1/*` request, applied by
     *    Modules\Core\Http\Middleware\ThrottleApiV1 (not throttleApi(), which
     *    only covers routes in the framework `api` group — most of our API
     *    lives in `web`/`storefront` because cart + account need a session).
     *    Generous: storefront JS fires bursts (suggest autocomplete, facet
     *    filters, cart sync).
     *  - `checkout` → order placement: a write that reserves stock and calls a
     *    payment gateway. Tight bucket, keyed per user/IP.
     *  - `auth` → brute-force guard on credential endpoints (login / register /
     *    token issue), applied per-route in the Customer module. Keyed by IP —
     *    these requests have no authenticated user yet.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
