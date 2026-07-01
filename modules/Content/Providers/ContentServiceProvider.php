<?php

namespace Modules\Content\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Collection as LunarCollection;
use Modules\Content\Services\MenuRenderer;
use Modules\Content\Services\SectionRenderer;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Data\SearchQuery;
use Modules\Theme\Support\AdminPages;

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

        // category-grid → collections
        $renderer->provide('category-grid', function (array $settings) {
            $limit = (int) ($settings['limit'] ?? 6);

            return [
                'collections' => LunarCollection::query()
                    ->with(['thumbnail'])
                    ->limit($limit)
                    ->get(),
            ];
        });

        // product-tabs → products (via the shared ProductService / search)
        $renderer->provide('product-tabs', function (array $settings) {
            $limit = (int) ($settings['limit'] ?? 8);

            $result = $this->app->make(ProductService::class)
                ->list(new SearchQuery(perPage: $limit));

            $result->items->loadMissing(['variants', 'thumbnail', 'brand']);

            return ['products' => $result->items];
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
