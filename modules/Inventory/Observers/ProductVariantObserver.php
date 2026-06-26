<?php

namespace Modules\Inventory\Observers;

use Lunar\Models\ProductVariant;
use Modules\Hook\Facades\Hook;
use Modules\Hook\Support\Hooks;
use Modules\Inventory\Services\BackInStockNotifier;

/**
 * Watches variant stock changes — the single place stock transitions are
 * detected. Drives back-in-stock emails AND broadcasts the inventory domain
 * events (restocked / out-of-stock / low-stock) on the shared hook plane so
 * other modules/plugins can react (purchasing, feeds, analytics).
 *
 * We compare the original vs. new value rather than relying on a status flag so
 * any path that changes stock triggers it. The decrement pipeline lowers stock
 * (never raises it past 0), so order placement never trips the restock branch.
 */
class ProductVariantObserver
{
    /** Stock at or below this (and > 0) is "low". */
    public const LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        protected BackInStockNotifier $notifier,
    ) {}

    public function updated(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('stock')) {
            return;
        }

        $previous = (int) $variant->getOriginal('stock');
        $current = (int) $variant->stock;

        // Restock: ≤0 → >0. Notify waiting shoppers + broadcast.
        if ($previous <= 0 && $current > 0) {
            $this->notifier->notify($variant);
            Hook::doAction(Hooks::INVENTORY_RESTOCKED, [$variant, $current]);
        }

        // Out of stock: >0 → ≤0.
        if ($previous > 0 && $current <= 0) {
            Hook::doAction(Hooks::INVENTORY_OUT_OF_STOCK, [$variant]);
        }

        // Low stock: crossed below the threshold (still in stock). Only on the
        // downward crossing so it fires once, not on every decrement under it.
        if ($current > 0 && $current <= self::LOW_STOCK_THRESHOLD && $previous > self::LOW_STOCK_THRESHOLD) {
            Hook::doAction(Hooks::INVENTORY_LOW_STOCK, [$variant, $current]);
        }
    }
}
