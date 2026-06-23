<?php

namespace Modules\Inventory\Observers;

use Lunar\Models\ProductVariant;
use Modules\Inventory\Services\BackInStockNotifier;

/**
 * Detects restocks. When a variant's `stock` changes from ≤0 to >0 (admin edit,
 * CSV import, manual top-up…), notify shoppers waiting on "back in stock".
 *
 * We compare the original vs. new value rather than relying on a status flag so
 * any path that raises stock triggers it. The decrement pipeline lowers stock
 * (never raises it past 0), so order placement never trips this.
 */
class ProductVariantObserver
{
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

        if ($previous <= 0 && $current > 0) {
            $this->notifier->notify($variant);
        }
    }
}
