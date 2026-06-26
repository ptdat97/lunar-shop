<?php

namespace Modules\Platform\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Platform\Events\EventBridge;
use Modules\Platform\Plugin\PluginManager;
use Modules\Platform\Services\HookManager;

class PlatformServiceProvider extends ServiceProvider
{
    /**
     * Register the shared HookManager. Singleton so every module sees the same
     * listener registry. Registered first (Platform is top of
     * ModulesServiceProvider) so later modules can add filters/actions in their
     * own register().
     *
     * The PluginManager singleton is defined here too, but its load()/boot() are
     * driven by ModulesServiceProvider AFTER the core modules — plugins extend a
     * fully-wired app, so they can't register before the hooks they target exist.
     */
    public function register(): void
    {
        $this->app->singleton(HookManager::class, fn () => new HookManager);

        $this->app->singleton(PluginManager::class, fn ($app) => new PluginManager($app));

        $this->app->singleton(EventBridge::class, fn ($app) => new EventBridge($app->make(HookManager::class)));

        $this->mergeConfigFrom(base_path('config/plugins.php'), 'plugins');

        // Contribute the Plugins admin page to Lunar's panel (collected during
        // register, before the panel is built).
        \Modules\Platform\Support\AdminPages::add(\Modules\Platform\Filament\Pages\PluginsPage::class);
    }

    /**
     * Bootstrap module: routes.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'platform');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Platform\Console\PluginListCommand::class,
                \Modules\Platform\Console\PluginInstallCommand::class,
                \Modules\Platform\Console\PluginDisableCommand::class,
                \Modules\Platform\Console\PluginUninstallCommand::class,
                \Modules\Platform\Console\PluginDoctorCommand::class,
            ]);
        }
    }
}
