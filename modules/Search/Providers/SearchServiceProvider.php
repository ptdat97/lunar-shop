<?php

namespace Modules\Search\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Drivers\DatabaseSearchEngine;

class SearchServiceProvider extends ServiceProvider
{
    /**
     * Storefront/API talk only to the SearchEngine contract, so swapping the
     * implementation later (e.g. Meilisearch) is a one-line binding change.
     */
    public function register(): void
    {
        $this->app->singleton(SearchEngine::class, DatabaseSearchEngine::class);
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
