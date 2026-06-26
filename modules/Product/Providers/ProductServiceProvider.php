<?php

namespace Modules\Product\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Product;
use Modules\Product\Models\ProductMaterial;
use Modules\Product\Models\SizeChart;

class ProductServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // Standalone Size Charts resource (Catalog group). Registered here
        // because ModulesServiceProvider collects resources during register().
        \Modules\Theme\Support\AdminPages::addResource(
            \Modules\Product\Filament\Resources\SizeChartResource::class,
        );
    }

    /**
     * Bootstrap module: routes, migrations, views, model relationships.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->registerSizeRelationships();

        // Broadcast product create/update on the shared hook plane.
        Product::observe(\Modules\Product\Observers\ProductObserver::class);
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
}
