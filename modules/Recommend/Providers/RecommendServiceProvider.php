<?php

namespace Modules\Recommend\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Recommend\Services\RecommendationService;

class RecommendServiceProvider extends ServiceProvider
{
    /**
     * Bind the RecommendationService with its configured strategy chain.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/recommend.php'), 'recommend');

        $this->app->singleton(RecommendationService::class, function ($app) {
            $strategies = collect(config('recommend.strategies', []))
                ->map(fn ($class) => $app->make($class))
                ->all();

            return new RecommendationService(
                strategies: $strategies,
                cacheTtl: (int) config('recommend.cache_ttl', 3600),
            );
        });
    }

    /**
     * Bootstrap module: routes.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
