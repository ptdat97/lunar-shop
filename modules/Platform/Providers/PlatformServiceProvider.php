<?php

namespace Modules\Platform\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Platform\Events\EventBridge;
use Modules\Platform\Plugin\PluginManager;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Services\HookManager;
use Modules\Platform\Workflow\WorkflowEngine;
use Modules\Platform\Workflow\WorkflowRegistry;

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

        // Rule engine registry — modules/plugins register fields (cart.subtotal…);
        // Core ships none (stays business-free).
        $this->app->singleton(RuleRegistry::class, fn () => new RuleRegistry);

        // Workflow engine: registry of triggers/actions + the orchestrator.
        $this->app->singleton(WorkflowRegistry::class, fn () => new WorkflowRegistry);
        $this->app->singleton(WorkflowEngine::class, fn ($app) => new WorkflowEngine(
            $app->make(WorkflowRegistry::class),
            $app->make(RuleRegistry::class),
            $app->make(HookManager::class),
        ));

        // Subscribe to trigger hooks once EVERYTHING (modules + plugins) has
        // booted and registered its triggers — booted() fires after all boot().
        $this->app->booted(function (): void {
            $this->app->make(WorkflowEngine::class)->listen();
        });

        $this->mergeConfigFrom(base_path('config/plugins.php'), 'plugins');

        // Contribute the Platform admin pages to Lunar's panel (collected during
        // register, before the panel is built).
        \Modules\Platform\Support\AdminPages::add(
            \Modules\Platform\Filament\Pages\PluginsPage::class,
            \Modules\Platform\Filament\Pages\WorkflowsPage::class,
        );
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
                \Modules\Platform\Console\PlatformDoctorCommand::class,
            ]);
        }
    }
}
