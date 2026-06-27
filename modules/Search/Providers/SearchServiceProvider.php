<?php

namespace Modules\Search\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Services\SearchManager;

class SearchServiceProvider extends ServiceProvider
{
    /**
     * Register the driver registry + bind the active SearchEngine through it.
     * Plugins can SearchManager::extend(...) a driver at boot; the active one
     * (config('search.driver')) is resolved lazily when SearchEngine is needed.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/search.php'), 'search');

        $this->app->singleton(SearchManager::class, fn ($app) => new SearchManager($app));

        $this->app->singleton(SearchEngine::class, fn ($app) => $app->make(SearchManager::class)->driver());
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
