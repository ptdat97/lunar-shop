<?php

namespace Modules\Inventory\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Events\Orders\OrderCancelled;
use Lunar\Core\Facades\CancelReasons;
use Lunar\Core\Models\ProductVariant;
use Lunar\Panel\Facades\Panel;
use Modules\Core\Panel\ResourceRegistry;
use Modules\Core\Panel\SettingsRegistry;
use Modules\Core\Support\LunarConfigOverride;
use Modules\Inventory\Console\ExpireAbandonedOrders;
use Modules\Inventory\Listeners\StampStockReleased;
use Modules\Inventory\Observers\BackInStockObserver;
use Modules\Inventory\Panel\InventorySection;
use Modules\Inventory\Panel\InventorySettings;
use Modules\Inventory\Panel\StockNotificationResource;
use Modules\Inventory\Services\InventoryService;
use Throwable;

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
        // Thẻ cảnh báo đơn giữ hàng quá lâu trên dashboard panel.
        Panel::section(new InventorySection);

        $this->feedLunarsLowStockThreshold();
        $this->registerCancelReason();

        // Nhóm cài đặt của module trên panel Lunar.
        $this->app->make(SettingsRegistry::class)->add(new InventorySettings);

        // Màn hình admin của module trên panel Lunar.
        $this->app->make(ResourceRegistry::class)->add(new StockNotificationResource);

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

    /**
     * Point Lunar's own low-stock widget at this shop's configured threshold.
     *
     * The widget is first-party and stays that way — it already knows to skip
     * variants sold regardless of stock and to ignore invisible products, and
     * rebuilding that would be duplicating upstream work. What it cannot know
     * is that this shop lets staff set the threshold from the panel.
     *
     * Without this the settings field was decoration: an admin set 5, and the
     * dashboard went on using Lunar's default of 10.
     *
     * Deferred to `booted()` and wrapped, because `Settings` reads a table:
     * during `migrate` on an empty database there is nothing to read, and the
     * right answer then is Lunar's default rather than a fatal.
     */
    protected function feedLunarsLowStockThreshold(): void
    {
        $this->app->booted(function (): void {
            try {
                $threshold = $this->app->make(InventoryService::class)->lowStockThreshold();
            } catch (Throwable) {
                return;
            }

            config(['lunar.panel.dashboard.low_stock_threshold' => $threshold]);
        });
    }

    /**
     * Teach Lunar's cancel-reason vocabulary about the one this shop adds.
     *
     * `orders:expire-abandoned` cancels through Lunar's own CancelOrder action
     * and stamps the reason `abandoned`. That reason was not in the manifest,
     * and `ReasonManifest::label()` falls back to the raw key — so the panel's
     * order screen showed a staff member the literal word "abandoned" where
     * every other cancellation reads as a sentence.
     *
     * Registering it rather than formatting the string ourselves is the point:
     * the label then comes from the same place the panel's cancel dialog and
     * `cancelReasonLabel()` already read.
     */
    protected function registerCancelReason(): void
    {
        CancelReasons::add(
            ExpireAbandonedOrders::CANCEL_REASON,
            'admin.orders.cancel_reason_abandoned',
        );
    }
}
