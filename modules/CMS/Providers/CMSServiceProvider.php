<?php

namespace Modules\CMS\Providers;

use Illuminate\Support\ServiceProvider;

class CMSServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // Register CMS Filament resources into Lunar's admin panel.
        // Must be in register() because ModulesServiceProvider collects
        // resources in register() after all module providers have registered.
        \Modules\Platform\Support\AdminPages::addResource(
            \Modules\CMS\Filament\Resources\PageResource::class,
            \Modules\CMS\Filament\Resources\BannerResource::class,
            \Modules\CMS\Filament\Resources\LookbookResource::class,
            \Modules\CMS\Filament\Resources\RedirectResource::class,
        );
    }

    /**
     * Bootstrap module: routes, migrations, views.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
