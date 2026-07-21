<?php

namespace Modules\Inventory\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Models\ProductSku;
use Modules\Core\Support\AdminPages;
use Modules\Core\Support\LunarConfigOverride;
use Modules\Inventory\Console\ExpireAbandonedOrders;
use Modules\Inventory\Filament\Pages\InventorySettingsPage;
use Modules\Inventory\Filament\Pages\StockNotificationsPage;
use Modules\Inventory\Filament\Pages\StockOverview;
use Modules\Inventory\Listeners\ReleaseStockOnOrderClosed;
use Modules\Inventory\Observers\ProductSkuObserver;
use Modules\Order\Events\OrderStatusUpdated;

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
        AdminPages::add(StockNotificationsPage::class);
        AdminPages::add(InventorySettingsPage::class);
    }

    /**
     * Bootstrap module: routes, migrations, views, stock pipeline + observer.
     */
    public function boot(): void
    {
        // Append our DecrementStock pipeline to Lunar's order-creation pipeline
        // (reserve stock + oversell guard). Re-applied here so it survives
        // `vendor:publish --tag=lunar --force`.
        LunarConfigOverride::applyFrom('lunar.orders', __DIR__.'/../../config/overrides.php');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'inventory');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Back-in-stock: notify subscribers when a variant is restocked.
        ProductSku::observe(ProductSkuObserver::class);

        // Stock is reserved when the order row is created — before payment, for
        // a gateway order. Give it back when the order is cancelled or refunded,
        // or those units are destroyed for good.
        Event::listen(OrderStatusUpdated::class, ReleaseStockOnOrderClosed::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireAbandonedOrders::class]);
        }
    }
}
