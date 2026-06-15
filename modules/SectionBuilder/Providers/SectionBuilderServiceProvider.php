<?php

namespace Modules\SectionBuilder\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Collection as LunarCollection;
use Modules\Product\Services\ProductService;
use Modules\Search\Data\SearchQuery;
use Modules\SectionBuilder\Services\SectionRenderer;

class SectionBuilderServiceProvider extends ServiceProvider
{
    /**
     * Register the section renderer + admin resource.
     */
    public function register(): void
    {
        $this->app->singleton(SectionRenderer::class);

        \Modules\Theme\Support\AdminPages::addResource(
            \Modules\SectionBuilder\Filament\Resources\PageSectionResource::class,
        );
    }

    /**
     * Bootstrap: migrations + data providers for dynamic sections.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->registerSectionData();
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
    }
}
