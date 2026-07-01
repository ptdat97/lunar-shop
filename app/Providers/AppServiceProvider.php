<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The Lunar admin panel is registered by ModulesServiceProvider after all
     * module providers have registered, so modules can contribute admin pages.
     */
    public function register(): void
    {
        // Admin-configurable feature settings (payment/shipping/membership/…),
        // DB-backed with config/env fallback. Singleton so the per-request cache
        // read is shared.
        $this->app->singleton(Settings::class);
    }
}
