<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Support\Settings;

/**
 * Core module: shared, cross-cutting infrastructure only (settings store, queue
 * names, admin-page collector, Lunar config override helper). No business logic
 * lives here — it's the base layer every feature module builds on. Registered
 * first so its bindings are available to all other module providers.
 */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin-configurable feature settings (payment/shipping/membership/…),
        // DB-backed with config/env fallback. Singleton so the per-request cache
        // read is shared.
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // The shared app_settings table lives with the Core infrastructure.
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
