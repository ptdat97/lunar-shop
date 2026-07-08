<?php

namespace Modules\Content\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Modules\Content\Services\MenuRenderer;
use Modules\Content\Services\SectionRenderer;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Data\SearchQuery;
use Modules\Core\Support\AdminPages;

class ContentServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings + admin resources.
     */
    public function register(): void
    {
        $this->app->singleton(SectionRenderer::class);
        $this->app->singleton(MenuRenderer::class);

        // Register Content Filament resources into Lunar's admin panel.
        // Must be in register() because ModulesServiceProvider collects
        // resources in register() after all module providers have registered.
        AdminPages::addResource(
            \Modules\Content\Filament\Resources\PageResource::class,
            \Modules\Content\Filament\Resources\BannerResource::class,
            \Modules\Content\Filament\Resources\LookbookResource::class,
            \Modules\Content\Filament\Resources\RedirectResource::class,
            \Modules\Content\Filament\Resources\PageSectionResource::class,
            \Modules\Content\Filament\Resources\MenuResource::class,
        );
    }

    /**
     * Bootstrap module: routes, migrations, views.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->registerSectionData();
        $this->composeMenus();
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

            $urls = $this->app->make(\Modules\Assets\Services\MediaUrl::class);

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
                    // prices.currency so the card price renders without a per-grid
                    // currency lazy-load; media powers the hover image.
                    ->with(['variants.prices.currency', 'thumbnail', 'brand', 'media'])
                    ->get()
                    ->keyBy('id');

            // Shared fallback (newest) for tabs with no explicit selection.
            $fallback = null;
            $resolveFallback = function () use (&$fallback, $fallbackLimit) {
                if ($fallback === null) {
                    $result = $this->app->make(ProductService::class)
                        ->list(new SearchQuery(perPage: $fallbackLimit));
                    $result->items->loadMissing(['variants', 'thumbnail', 'brand', 'media']);
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

        // promotion-slider → on-sale products (via the shared PromotionService).
        // `promotion` setting pins to one promotion handle; empty = all on-sale.
        $renderer->provide('promotion-slider', function (array $settings) {
            $promotions = $this->app->make(\Modules\Promotion\Services\PromotionService::class);
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
        if (str_starts_with($path, '/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return \Illuminate\Support\Facades\Storage::disk('media')->url($path);
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
