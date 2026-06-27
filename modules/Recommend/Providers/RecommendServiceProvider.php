<?php

namespace Modules\Recommend\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Recommend\Services\RecommendationService;
use Modules\Recommend\Services\RecommendManager;

class RecommendServiceProvider extends ServiceProvider
{
    /**
     * Bind the strategy registry (config + plugin-extendable) and the service
     * that reads from it. Plugins call RecommendManager::extend(...) at boot.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/recommend.php'), 'recommend');

        $this->app->singleton(RecommendManager::class, fn ($app) => new RecommendManager($app));

        $this->app->singleton(RecommendationService::class, fn ($app) => new RecommendationService(
            manager: $app->make(RecommendManager::class),
            cacheTtl: (int) config('recommend.cache_ttl', 3600),
        ));
    }

    /**
     * Bootstrap module: routes.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
