<?php

namespace Modules\Search\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Search\Contracts\SearchEngine;

class SearchServiceProvider extends ServiceProvider
{
    /**
     * Bind the active SearchEngine driver from config.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/search.php'), 'search');

        $this->app->singleton(SearchEngine::class, function ($app) {
            $driver = config('search.driver', 'database');
            $class = config("search.drivers.{$driver}");

            abort_unless($class, 500, "Unknown search driver [{$driver}].");

            return $app->make($class);
        });
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
