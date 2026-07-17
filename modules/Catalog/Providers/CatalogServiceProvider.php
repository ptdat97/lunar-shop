<?php

namespace Modules\Catalog\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Product;
use Modules\Catalog\Console\Commands\MigrateVariantsToSkus;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Drivers\DatabaseSearchEngine;
use Modules\Catalog\Filament\Pages\CatalogSettingsPage;
use Modules\Catalog\Filament\Resources\SizeChartResource;
use Modules\Catalog\Models\ProductMaterial;
use Modules\Catalog\Models\ProductSku;
use Modules\Catalog\Models\SizeChart;
use Modules\Catalog\Services\PricingService;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\RecommendationService;
use Modules\Core\Support\AdminPages;
use Modules\Core\Support\Settings;

class CatalogServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings + admin resources.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/review.php'), 'review');
        $this->mergeConfigFrom(base_path('config/recommend.php'), 'recommend');

        // Scoped (per request, Octane-safe): holds per-request memos of matched
        // prices and the currency map used to prime price->currency.
        $this->app->scoped(PricingService::class);

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
                cacheTtl: (int) app(Settings::class)->get('recommend.cache_ttl', 3600),
            );
        });

        // Standalone Size Charts resource (Catalog group). Registered here
        // because ModulesServiceProvider collects resources during register().
        AdminPages::addResource(
            SizeChartResource::class,
        );

        // Catalog settings page (recommendation limits + review moderation).
        AdminPages::add(CatalogSettingsPage::class);
    }

    /**
     * Bootstrap module: routes, migrations, model relationships, view composers.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'catalog-admin');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        $this->registerSizeRelationships();
        $this->registerSkuRelationships();
        $this->composeThemePrices();

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateVariantsToSkus::class,
            ]);
        }
    }

    /**
     * Wire the flexible SKU model (VaniCommerce-style variants) into Lunar:
     *
     *  - morph map: cart/order lines store `purchasable_type = product_sku`
     *    (Lunar snake-cases model basenames for its own morph aliases, so this
     *    matches that convention). Relation::morphMap MERGES, so Lunar's own
     *    aliases are preserved.
     *  - Product::skus  hasMany the SKU rows for a product.
     *
     * Registered without touching the vendor Product class (plan principle #1).
     */
    protected function registerSkuRelationships(): void
    {
        Relation::morphMap([
            'product_sku' => ProductSku::class,
        ]);

        // Cast the free-form `variables` JSON on every Product without
        // subclassing the vendor model (Lunar exposes no cast extension point).
        // mergeCasts is per-instance and idempotent. Apply it both on read
        // (retrieved) AND before write (saving) — without the write-side cast an
        // array assigned to `variables` is not JSON-encoded and silently drops.
        $castVariables = fn (Product $product) => $product->mergeCasts(['variables' => 'array']);
        Product::retrieved($castVariables);
        Product::saving($castVariables);

        Product::resolveRelationUsing(
            'skus',
            fn (Product $product) => $product->hasMany(ProductSku::class, 'product_id')
                ->orderBy('position'),
        );
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
            // variant. The controller already resolved it (passed as
            // $selectedVariant) — reuse instead of resolving twice per request.
            $selectedVariant = $view->getData()['selectedVariant']
                ?? ($product
                    ? $this->app->make(ProductService::class)
                        ->resolveSelectedVariant($product, request()->query())
                    : null);

            $view->with([
                'displayPrice' => $selectedVariant
                    ? $svc->displayPriceForVariant($selectedVariant)
                    : ($product ? $svc->displayPrice($product) : null),
                'lowestPriceAmount' => $product ? $svc->lowestPriceAmount($product) : null,
                'currencyCode' => $svc->defaultCurrencyCode(),
            ]);
        });

        // Recently-viewed strip: inject the admin-configured display limit
        // (Catalog settings) so Blade doesn't resolve Settings itself (§7) and
        // enhance/recently-viewed.js reads it from a data-* attribute instead of
        // a hardcoded number.
        View::composer('theme::partials.recently-viewed', function ($view): void {
            $limit = (int) app(Settings::class)
                ->get('recently_viewed.limit', 8);
            $view->with('recentlyViewedLimit', max(1, min(12, $limit)));
        });
    }
}
