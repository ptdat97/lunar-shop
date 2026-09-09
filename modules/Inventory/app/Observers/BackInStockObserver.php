<?php

namespace Modules\Inventory\Observers;

use Lunar\Core\Models\ProductVariant;
use Modules\Inventory\Services\BackInStockNotifier;

/**
 * Tells shoppers waiting on a size that it is sellable again.
 *
 * The one piece of stock handling Lunar does not do for us. It watches
 * `stock_available` — the rollup, not `on_hand` — because that is the number a
 * shopper can actually buy: units physically present but committed to someone
 * else's order are not back in stock.
 *
 * An observer works here, unlike the order lifecycle: `RecomputeStockRollup`
 * writes the variant with a plain `save()`, so model events fire. (The order
 * rollups are written with `saveQuietly()` and needed listeners instead — see
 * docs/guides/upgrade-lunar-2.0.md §9.6. Worth knowing the two differ.)
 *
 * Fires only on the 0 → positive edge, so a restock of an already-sellable
 * variant sends nothing.
 */
class BackInStockObserver
{
    public function __construct(
        protected BackInStockNotifier $notifier,
    ) {}

    public function updated(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('stock_available')) {
            return;
        }

        $previous = (int) $variant->getOriginal('stock_available');
        $current = (int) $variant->stock_available;

        if ($previous <= 0 && $current > 0) {
            $this->notifier->notify($variant);
        }
    }
}
