<?php

namespace Modules\Hook\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Hook\Services\HookManager;

class HookServiceProvider extends ServiceProvider
{
    /**
     * Register the shared HookManager. Singleton so every module sees the same
     * listener registry. Registered first (Hook is top of ModulesServiceProvider)
     * so later modules can add filters/actions in their own register().
     */
    public function register(): void
    {
        $this->app->singleton(HookManager::class, fn () => new HookManager);
    }

    /**
     * Bootstrap module: routes.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
