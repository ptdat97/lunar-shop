<?php

namespace Modules\Hook\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Hook\Plugin\PluginManager;
use Modules\Hook\Services\HookManager;

class HookServiceProvider extends ServiceProvider
{
    /**
     * Register the shared HookManager. Singleton so every module sees the same
     * listener registry. Registered first (Hook is top of ModulesServiceProvider)
     * so later modules can add filters/actions in their own register().
     *
     * The PluginManager singleton is defined here too, but its load()/boot() are
     * driven by ModulesServiceProvider AFTER the core modules — plugins extend a
     * fully-wired app, so they can't register before the hooks they target exist.
     */
    public function register(): void
    {
        $this->app->singleton(HookManager::class, fn () => new HookManager);

        $this->app->singleton(PluginManager::class, fn ($app) => new PluginManager($app));

        $this->mergeConfigFrom(base_path('config/plugins.php'), 'plugins');

        // Contribute the Plugins admin page to Lunar's panel (collected during
        // register, before the panel is built).
        \Modules\Theme\Support\AdminPages::add(\Modules\Hook\Filament\Pages\PluginsPage::class);
    }

    /**
     * Bootstrap module: routes.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'hook');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Hook\Console\PluginListCommand::class,
                \Modules\Hook\Console\PluginInstallCommand::class,
                \Modules\Hook\Console\PluginDisableCommand::class,
                \Modules\Hook\Console\PluginUninstallCommand::class,
                \Modules\Hook\Console\PluginDoctorCommand::class,
            ]);
        }
    }
}
