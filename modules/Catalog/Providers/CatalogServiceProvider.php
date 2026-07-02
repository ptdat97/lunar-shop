<?php

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Product;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Drivers\DatabaseSearchEngine;
use Modules\Catalog\Models\ProductMaterial;
use Modules\Catalog\Models\SizeChart;
use Modules\Catalog\Services\PricingService;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\RecommendationService;
use Modules\Core\Support\AdminPages;

class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings + admin resources.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/review.php'), 'review');
        $this->mergeConfigFrom(base_path('config/recommend.php'), 'recommend');

        $this->app->singleton(PricingService::class);

        // Storefront/API talk only to the SearchEngine contract, so swapping the
        // implementation later (e.g. Meilisearch) is a one-line binding change.
        $this->app->singleton(SearchEngine::class, DatabaseSearchEngine::class);

        // RecommendationService with its configured strategy chain (curated
        // associations first, collection fallback after).
        $this->app->singleton(RecommendationService::class, function ($app) {
            $strategies = collect(config('recommend.strategies', []))
                ->map(fn ($class) => $app->make($class))
                ->all();

            return new RecommendationService(
                strategies: $strategies,
                cacheTtl: (int) app(\Modules\Core\Support\Settings::class)->get('recommend.cache_ttl', 3600),
            );
        });

        // Standalone Size Charts resource (Catalog group). Registered here
        // because ModulesServiceProvider collects resources during register().
        AdminPages::addResource(
            \Modules\Catalog\Filament\Resources\SizeChartResource::class,
        );

        // Catalog settings page (recommendation limits + review moderation).
        AdminPages::add(\Modules\Catalog\Filament\Pages\CatalogSettingsPage::class);
    }

    /**
     * Bootstrap module: routes, migrations, model relationships, view composers.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'catalog-admin');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->registerSizeRelationships();
        $this->composeThemePrices();
    }

    /**
     * Attach fashion sizing relationships to Lunar's Product without editing the
     * vendor class (plan principle #1: extend, don't fork):
     *
     *  - `material`   hasOne fabric/care info.
     *  - `sizeChart`  the single reusable chart assigned via the link table.
     */
    protected function registerSizeRelationships(): void
    {
        Product::resolveRelationUsing(
            'material',
            fn (Product $product) => $product->hasOne(ProductMaterial::class, 'product_id'),
        );

        Product::resolveRelationUsing(
            'sizeChart',
            fn (Product $product) => $product->belongsToMany(
                SizeChart::class,
                'product_size_chart',
                'product_id',
                'size_chart_id',
            ),
        );
    }

    /**
     * Inject formatted prices into theme views so Blade never resolves the
     * pricing service itself (coding standards §7).
     *  - price component → $formatted (first variant display price)
     *  - product page     → $displayPrice / $lowestPriceAmount / $currencyCode
     */
    protected function composeThemePrices(): void
    {
        $pricing = fn () => $this->app->make(PricingService::class);

        View::composer('theme::components.price', function ($view) use ($pricing): void {
            $product = $view->getData()['product'] ?? null;
            $view->with('formatted', $product ? $pricing()->displayPrice($product) : null);
        });

        View::composer('theme::pages.product', function ($view) use ($pricing): void {
            $svc = $pricing();
            $product = $view->getData()['product'] ?? null;

            // Price the deep-linked variant (?color=red&size=m) so the SSR price
            // is correct for no-JS visitors + crawlers; falls back to the first
            // variant. enhance/product-variant.js keeps this in sync client-side.
            $selectedVariant = $product
                ? $this->app->make(ProductService::class)
                    ->resolveSelectedVariant($product, request()->query())
                : null;

            $view->with([
                'displayPrice' => $selectedVariant
                    ? $svc->displayPriceForVariant($selectedVariant)
                    : ($product ? $svc->displayPrice($product) : null),
                'lowestPriceAmount' => $product ? $svc->lowestPriceAmount($product) : null,
                'currencyCode' => $svc->defaultCurrencyCode(),
            ]);
        });
    }
}
