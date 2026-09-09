<?php

namespace Modules\Analytics\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Panel\Facades\Panel;
use Modules\Analytics\Panel\AnalyticsSection;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/analytics.php', 'analytics');
    }

    public function boot(): void
    {
        // Một thẻ trên dashboard panel — phần Lunar không có: nhìn dài hơn 90 ngày.
        Panel::section(new AnalyticsSection);

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'analytics');
    }
}
