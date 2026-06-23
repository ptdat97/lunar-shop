<?php

namespace Modules\Analytics\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Analytics\Filament\Pages\AnalyticsDashboard;
use Modules\Theme\Support\AdminPages;

class AnalyticsServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings + contribute the dashboard to the admin panel.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/analytics.php', 'analytics');

        AdminPages::add(AnalyticsDashboard::class);
    }

    /**
     * Bootstrap module: routes, views.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'analytics');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
