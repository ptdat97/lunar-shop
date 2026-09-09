<?php

namespace Modules\Inventory\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Events\Orders\OrderCancelled;
use Lunar\Core\Models\ProductVariant;
use Modules\Core\Support\LunarConfigOverride;
use Modules\Inventory\Console\ExpireAbandonedOrders;
use Modules\Inventory\Listeners\StampStockReleased;
use Modules\Inventory\Observers\BackInStockObserver;

/**
 * Inventory is now mostly Lunar's.
 *
 * Lunar 2.0 ships the whole stock machinery this module used to carry, and
 * wires it to the order lifecycle itself:
 *
 *   OrderPlaced / OrderCancelled  → SyncStockForOrder      (commit / release)
 *   FulfilmentCreated             → AllocateStockForFulfilment
 *   fulfilment state transition   → ApplyStockForFulfilmentTransition
 *                                   (a `shipped` movement takes the units off
 *                                   the shelf, `returned` puts them back)
 *
 * So `DecrementStock`, `StockLedger`, `StockSettler`, `StockReleaser` and the
 * two listeners that drove them are gone. Their replacement is better in three
 * ways worth naming, because they were real gaps:
 *
 *  - `stock_committed` is DERIVED from the order book rather than being a
 *    counter this application incremented, so it cannot drift from reality —
 *    the failure the old ledger's `stock_before` / `stock_after` columns existed
 *    to detect after the fact.
 *  - un-shipping and returns move stock back; ours only moved it out.
 *  - stock is per location, which the shop had no way to express at all.
 *
 * What stays is what Lunar does not do: telling a shopper their size is back.
 */
class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // The last-line oversell guard, on Lunar's own validator hook. Re-applied
        // here so it survives `vendor:publish --tag=lunar --force`.
        LunarConfigOverride::applyFrom('lunar.cart', __DIR__.'/../../config/overrides.php');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'inventory');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Back-in-stock: notify subscribers when a variant becomes sellable
        // again. An observer is right here — unlike the order rollups, Lunar
        // writes the stock rollup with a plain `save()`, so model events fire.
        ProductVariant::observe(BackInStockObserver::class);

        // Lunar releases the stock on cancel but keeps no timestamp for it; the
        // stale-commitment warning and the abandoned-order sweep both need one.
        Event::listen(OrderCancelled::class, StampStockReleased::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireAbandonedOrders::class]);
        }
    }
}
