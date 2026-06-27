<?php

namespace Acme\Recommend;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Lunar\Models\Product;
use Modules\Platform\Plugin\BasePlugin;
use Modules\Platform\Services\HookManager;
use Modules\Platform\Support\Hooks;

/**
 * Recommendations as a first-party plugin (Phase 4: extracted from the Recommend
 * module). Product no longer depends on a recommender — its controllers run the
 * plain collection fallback through the `product.related` filter; this plugin
 * hooks that filter to return curated-first recommendations. With the plugin
 * disabled, the storefront degrades gracefully to the collection fallback.
 *
 * Enabled by default in config/plugins.php (default-on storefront feature).
 */
class RecommendPlugin extends BasePlugin
{
    protected string $dir;

    public function __construct()
    {
        $this->dir = dirname(__DIR__);
    }

    public function id(): string
    {
        return 'acme/recommend';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(Application $app): void
    {
        // Merge the plugin's config (strategy chain, limits, ttl) before bindings.
        $app['config']->set('recommend', array_merge(
            require $this->dir . '/config/recommend.php',
            (array) $app['config']->get('recommend', []),
        ));

        $app->singleton(RecommendManager::class, fn ($app) => new RecommendManager($app));

        $app->singleton(RecommendationService::class, fn ($app) => new RecommendationService(
            manager: $app->make(RecommendManager::class),
            cacheTtl: (int) config('recommend.cache_ttl', 3600),
        ));
    }

    public function boot(HookManager $hooks): void
    {
        Route::group([], $this->dir . '/routes/api.php');

        // Provide curated-first recommendations as the "related" set. The given
        // $fallback is the controller's plain collection result; forProduct()
        // already blends curated (associations) + collection, so we replace it.
        $hooks->addFilter(
            Hooks::PRODUCT_RELATED,
            function ($fallback, Product $product, int $limit = 8) {
                return app(RecommendationService::class)->forProduct($product, $limit);
            },
        );
    }
}
