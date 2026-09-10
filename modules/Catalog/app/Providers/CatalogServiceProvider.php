<?php

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Models\Brand;
use Lunar\Core\Models\Collection as LunarCollection;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductVariant;
use Lunar\Panel\Facades\Panel;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Drivers\DatabaseSearchEngine;
use Modules\Catalog\Models\ProductMaterial;
use Modules\Catalog\Models\SizeChart;
use Modules\Catalog\Panel\CatalogSection;
use Modules\Catalog\Panel\CatalogSettings;
use Modules\Catalog\Panel\ReviewResource;
use Modules\Catalog\Panel\SizeChartResource;
use Modules\Catalog\Services\PricingService;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\RecommendationService;
use Modules\Catalog\Services\ReviewService;
use Modules\Core\Casts\FilledTranslations;
use Modules\Core\Panel\ResourceRegistry;
use Modules\Core\Panel\SettingsRegistry;
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

        $this->guardEmptyTranslations();

        // Scoped (per request, Octane-safe): holds per-request memos of matched
        // prices and the currency map used to prime price->currency.
        $this->app->scoped(PricingService::class);

        // Scoped for the same reason: ReviewService memoises review summaries per
        // request. ProductResource embeds one per product, so a grid would
        // otherwise fire a count + an avg query for every card.
        $this->app->scoped(ReviewService::class);

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
    }

    /**
     * Bootstrap module: routes, migrations, model relationships, view composers.
     */
    public function boot(): void
    {
        // Thẻ "Size & Fit" gắn vào trang sửa sản phẩm của panel (không ghi đè gì).
        Panel::section(new CatalogSection);

        // Nhóm cài đặt của module trên panel Lunar.
        $this->app->make(SettingsRegistry::class)->add(new CatalogSettings);
        $this->app->make(ResourceRegistry::class)->add(new ReviewResource);

        // Màn hình admin của module trên panel Lunar.
        $this->app->make(ResourceRegistry::class)->add(new SizeChartResource);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'catalog-admin');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $this->registerSizeRelationships();
        $this->registerVariantExtensions();
        $this->composeThemePrices();

    }

    /**
     * Stop `translate()` handing back an empty string when the current locale's
     * key is present but blank.
     *
     * Lunar 1.x let us fix this by swapping the model — a `translate()` override
     * carried by four subclasses registered through `ModelManifest::replace()`.
     * **2.0 removed model replacement**: core names `ProductOptionValue::class`
     * and friends directly, and `ModelManifest` is now only route bindings plus
     * the morph map. `Base::addCasts()` (`HasExtendableCasts`) is the seam that
     * replaced it, so the fix moved from the read method down to the column —
     * strip blank locales as the JSON is decoded and upstream's `translate()`
     * is correct as written, because "key exists but is empty" cannot happen.
     *
     * The surface moved with 2.0 in both directions:
     *  - GONE: `lunar_attributes.name` / `lunar_attribute_groups.name` are plain
     *    `string` columns now, so those two subclasses had nothing left to do.
     *  - NEW: spec 0018 promoted `name` / `description` / `short_description`
     *    out of `attribute_data` into real JSON columns on products,
     *    collections and brands — read through `translate()`, so they are now
     *    exposed to the same bug and are covered here.
     *
     * Match the container each model already declared (`AsCollection` vs
     * `AsArrayObject`): admin forms and `toArray()` can tell the difference.
     */
    protected function guardEmptyTranslations(): void
    {
        $collection = FilledTranslations::asCollection();
        $arrayObject = FilledTranslations::asArrayObject();

        Product::addCasts([
            'name' => $collection,
            'description' => $collection,
            'short_description' => $collection,
        ]);

        LunarCollection::addCasts([
            'name' => $collection,
            'description' => $collection,
            'short_description' => $collection,
        ]);

        Brand::addCasts([
            'description' => $collection,
            'short_description' => $collection,
        ]);

        ProductOption::addCasts([
            'name' => $arrayObject,
            'label' => $arrayObject,
        ]);

        ProductOptionValue::addCasts([
            'name' => $arrayObject,
        ]);
    }

    /**
     * Extensions on Lunar's own ProductVariant, registered without touching the
     * vendor class (plan principle #1).
     *
     * The shop no longer has a purchasable of its own: cart and order lines
     * store Lunar's `product_variant` morph alias, and the axes come from its
     * shared ProductOptions. What is left here are casts for the two columns
     * this shop still adds, and the eager-load chaperones.
     */
    protected function registerVariantExtensions(): void
    {
        // `image_asset_ids` is the shop's own column on Lunar's variant: a list
        // of Media Library Asset ids the variant points at, not media it owns.
        // The catalogue holds 1,945 references resolving to 162 distinct assets,
        // so owning them would copy the same files twelve times over and defeat
        // the shared library the Assets module exists for. `addCasts()` is the
        // seam 2.0 leaves for exactly this.
        //
        // NOT named `images`: that shadows Lunar's own ProductVariant::images()
        // relation, because a real column always wins over a relation of the
        // same name in Eloquent. It cost a 500 on every product editor page
        // before the rename — see the rename migration.
        ProductVariant::addCasts(['image_asset_ids' => 'array']);

        // NOT a `resolveRelationUsing('variants', …)` override: Laravel only
        // consults a dynamic relation when the model has no such method, and
        // Lunar's Product defines `variants()` — so an override there is
        // silently ignored, which is exactly how the chaperone below went
        // missing once already. Lunar 2.0 also removed model replacement, so the
        // relation cannot be redefined at all. Callers add `->chaperone()` in
        // their eager-load closure instead; see ProductService.
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
