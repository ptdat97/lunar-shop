<?php

namespace Modules\Analytics\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Analytics\Filament\Pages\AnalyticsDashboard;
use Modules\Core\Support\AdminPages;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/analytics.php', 'analytics');

        // Contribute the sales dashboard to the admin panel.
        AdminPages::add(AnalyticsDashboard::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'analytics');
    }
}
