<?php

namespace Modules\Content\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Modules\Assets\Services\MediaUrl;
use Modules\Catalog\Data\SearchQuery;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\ProductService;
use Modules\Content\Filament\Resources\BannerResource;
use Modules\Content\Filament\Resources\LookbookResource;
use Modules\Content\Filament\Resources\MenuResource;
use Modules\Content\Filament\Resources\PageResource;
use Modules\Content\Filament\Resources\PageSectionResource;
use Modules\Content\Filament\Resources\RedirectResource;
use Modules\Content\Services\MenuRenderer;
use Modules\Content\Services\SectionRenderer;
use Modules\Core\Support\AdminPages;
use Modules\Promotion\Http\Resources\PromotionResource;
use Modules\Promotion\Services\PromotionService;

class ContentServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings + admin resources.
     */
    public function register(): void
    {
        // SectionRenderer must stay a SINGLETON: modules register their section
        // data providers on it during boot, which under Octane runs once per
        // worker — a scoped binding would flush those registrations away after
        // the first request. It holds no per-request state (its config cache
        // lives in the Cache store).
        $this->app->singleton(SectionRenderer::class);

        // MenuRenderer is pure per-request memo state (loaded menu trees + the
        // shared linked-collection map), so scoped is the correct lifetime.
        $this->app->scoped(MenuRenderer::class);

        // Register Content Filament resources into Lunar's admin panel.
        // Must be in register() because ModulesServiceProvider collects
        // resources in register() after all module providers have registered.
        AdminPages::addResource(
            PageResource::class,
            BannerResource::class,
            LookbookResource::class,
            RedirectResource::class,
            PageSectionResource::class,
            MenuResource::class,
        );
    }

    /**
     * Bootstrap module: routes, migrations, views.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        $this->registerSectionData();
        $this->registerSectionPayloads();
        $this->composeMenus();
    }

    /**
     * How each dynamic section becomes JSON for `GET /api/v1/home-feed`.
     *
     * These reuse the SAME providers the Blade partials get, then map the
     * Eloquent models through the shared API Resources — so a headless client
     * and the SSR home page can never show different data, and no model
     * internals reach the wire. A section left unmapped here is omitted from
     * the feed (SectionRenderer::sectionPayload), not serialised raw.
     *
     * `promotions-strip` is serialised by the Promotion module: it owns the data.
     */
    protected function registerSectionPayloads(): void
    {
        $renderer = $this->app->make(SectionRenderer::class);

        // collection-grid's provider already returns flat presentation arrays
        // (name/url/image) — no models to map.
        $renderer->serialize('collection-grid', fn (array $data) => [
            'items' => array_values($data['items']->all()),
        ]);

        $renderer->serialize('product-tabs', fn (array $data) => [
            'tabs' => $data['tabs']->map(fn (array $tab) => [
                'label' => $tab['label'],
                'products' => ProductResource::collection($tab['products'])->resolve(),
            ])->all(),
        ]);

        $renderer->serialize('flash-sale', fn (array $data) => [
            'flash_sale' => $data['flashSale']
                ? (new PromotionResource($data['flashSale']))->resolve()
                : null,
            'products' => ProductResource::collection($data['products'])->resolve(),
        ]);

        $renderer->serialize('promotion-slider', fn (array $data) => [
            'pinned_promotion' => $data['pinnedPromotion']
                ? (new PromotionResource($data['pinnedPromotion']))->resolve()
                : null,
            'products' => ProductResource::collection($data['products'])->resolve(),
        ]);
    }

    /**
     * Wire dynamic sections to Lunar data via module services (inherited,
     * not duplicated). Static sections need no provider.
     */
    protected function registerSectionData(): void
    {
        $renderer = $this->app->make(SectionRenderer::class);

        // collection-grid → admin-curated collections with a per-item image
        // override. Each item picks a Lunar Collection (collection_id) and may
        // upload its own image; a blank image falls back to the collection's
        // thumbnail (self-healed via MediaUrl). The provider returns flat,
        // presentation-ready items so the Blade view resolves no service (§7).
        $renderer->provide('collection-grid', function (array $settings) {
            $items = $settings['items'] ?? [];

            // Load every picked collection ONCE (N+1-free), preserving admin order.
            $ids = collect($items)
                ->map(fn ($item) => (int) ($item['collection_id'] ?? 0))
                ->filter()
                ->unique()
                ->values();

            $byId = $ids->isEmpty()
                ? collect()
                : LunarCollection::query()
                    ->with(['thumbnail'])
                    ->whereIn('id', $ids)
                    ->get()
                    ->keyBy('id');

            $urls = $this->app->make(MediaUrl::class);

            $resolved = collect($items)
                ->map(function ($item) use ($byId, $urls) {
                    $collection = $byId->get((int) ($item['collection_id'] ?? 0));

                    if (! $collection) {
                        return null; // picked collection was deleted — drop it.
                    }

                    $override = trim((string) ($item['image'] ?? ''));

                    return [
                        'name' => $collection->translateAttribute('name'),
                        'url' => $collection->defaultUrl?->slug
                            ? route('storefront.collection', $collection->defaultUrl->slug)
                            : '#',
                        // Uploaded image wins; blank → collection thumbnail.
                        'image' => $override !== ''
                            ? $this->sectionImageUrl($override)
                            : $urls->conversion($collection->thumbnail, 'medium'),
                    ];
                })
                ->filter()
                ->values();

            return ['items' => $resolved];
        });

        // product-tabs → per-tab products. Each tab has an editable label and its
        // own hand-picked product_ids (PageSectionResource). We load every
        // referenced product ONCE (de-duped across tabs, N+1-free) then map each
        // tab to its products in the chosen order. A tab with no selection falls
        // back to the newest products so a freshly added tab still shows something.
        $renderer->provide('product-tabs', function (array $settings) {
            $tabs = $settings['tabs'] ?? [];
            $fallbackLimit = (int) ($settings['limit'] ?? 8);

            // Collect every selected id across all tabs → one query.
            $allIds = collect($tabs)
                ->flatMap(fn ($tab) => $tab['product_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $byId = $allIds->isEmpty()
                ? collect()
                : Product::query()
                    ->whereIn('id', $allIds)
                    ->where('status', 'published')
                    // PricingService primes price->currency from its per-request
                    // currency map, so `prices` alone is enough here; media
                    // powers the hover image.
                    ->with(['skus.prices', 'thumbnail', 'brand', 'media'])
                    ->get()
                    ->keyBy('id');

            // Shared fallback (newest) for tabs with no explicit selection.
            $fallback = null;
            $resolveFallback = function () use (&$fallback, $fallbackLimit) {
                if ($fallback === null) {
                    // withFacets:false → skip the facet/count aggregates a carousel
                    // never uses. The search engine already eager-loads variants,
                    // thumbnail, brand and media, so no follow-up loadMissing.
                    $result = $this->app->make(ProductService::class)
                        ->list(new SearchQuery(perPage: $fallbackLimit, withFacets: false));
                    $fallback = $result->items;
                }

                return $fallback;
            };

            $resolvedTabs = collect($tabs)->map(function ($tab) use ($byId, $resolveFallback) {
                $ids = collect($tab['product_ids'] ?? [])->map(fn ($id) => (int) $id)->filter();

                $products = $ids->isEmpty()
                    ? $resolveFallback()
                    // Preserve the admin's chosen order; drop ids that no longer resolve.
                    : $ids->map(fn ($id) => $byId->get($id))->filter()->values();

                return [
                    'label' => $tab['label'] ?? '',
                    'products' => $products,
                ];
            })->values();

            return [
                'tabs' => $resolvedTabs,
                // First tab's products power the SSR/no-JS default grid.
                'products' => $resolvedTabs->first()['products'] ?? collect(),
            ];
        });

        // flash-sale → the soonest-to-expire flash sale (`data.flash_sale` = true
        // + ends_at) with its covered products and a describe() summary. Renders
        // nothing when no flash sale is running, so the section can stay enabled
        // year-round and light up only during a sale.
        $renderer->provide('flash-sale', function (array $settings) {
            $promotions = $this->app->make(PromotionService::class);
            $flashSale = $promotions->currentFlashSale();

            return [
                'flashSale' => $flashSale,
                'flashSaleDescription' => $flashSale ? $promotions->describe($flashSale) : null,
                'products' => $flashSale
                    ? $promotions->productsForPromotion($flashSale, (int) ($settings['limit'] ?? 8))
                    : collect(),
            ];
        });

        // promotion-slider → on-sale products (via the shared PromotionService).
        // `promotion` setting pins to one promotion handle; empty = all on-sale.
        $renderer->provide('promotion-slider', function (array $settings) {
            $promotions = $this->app->make(PromotionService::class);
            $limit = (int) ($settings['limit'] ?? 12);
            $handle = trim((string) ($settings['promotion'] ?? ''));

            $pinned = $handle !== '' ? $promotions->findPromotion($handle) : null;

            return [
                'products' => $pinned
                    ? $promotions->productsForPromotion($pinned, $limit)
                    : $promotions->productsOnSale($limit),
                'pinnedPromotion' => $pinned,
            ];
        });
    }

    /**
     * Normalise a section FileUpload path into a usable <img src>. Uploads on the
     * `media` disk are stored as a relative path (e.g. sections/collection/x.jpg),
     * which needs the disk's public prefix; already-absolute values (seed demo
     * paths like /demo/…, or full URLs) are returned untouched.
     */
    protected function sectionImageUrl(string $path): string
    {
        // Shared resolver in modules/Assets (path/URL → browser URL).
        return media_url($path) ?? '';
    }

    /**
     * Expose the menu renderer to header/footer so Blade doesn't resolve a
     * service itself (coding standards §7).
     */
    protected function composeMenus(): void
    {
        View::composer(
            ['theme::partials.header', 'theme::partials.footer'],
            fn ($view) => $view->with('menus', $this->app->make(MenuRenderer::class)),
        );
    }
}
