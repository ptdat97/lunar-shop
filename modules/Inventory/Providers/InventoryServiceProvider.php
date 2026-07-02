<?php

namespace Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\ProductVariant;
use Modules\Inventory\Filament\Pages\StockOverview;
use Modules\Inventory\Observers\ProductVariantObserver;
use Modules\Core\Support\AdminPages;
use Modules\Core\Support\LunarConfigOverride;

class InventoryServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // Contribute the stock-overview (low/out-of-stock) page to the panel.
        AdminPages::add(StockOverview::class);
        // Back-in-stock mailing list ("notify me" subscriptions).
        AdminPages::add(\Modules\Inventory\Filament\Pages\StockNotificationsPage::class);
        AdminPages::add(\Modules\Inventory\Filament\Pages\InventorySettingsPage::class);
    }

    /**
     * Bootstrap module: routes, migrations, views, stock pipeline + observer.
     */
    public function boot(): void
    {
        // Append our DecrementStock pipeline to Lunar's order-creation pipeline
        // (reserve stock + oversell guard). Re-applied here so it survives
        // `vendor:publish --tag=lunar --force`.
        LunarConfigOverride::applyFrom('lunar.orders', __DIR__ . '/../Config/overrides.php');

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'inventory');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        // Back-in-stock: notify subscribers when a variant is restocked.
        ProductVariant::observe(ProductVariantObserver::class);
    }
}
