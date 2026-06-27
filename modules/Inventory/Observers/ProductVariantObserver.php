<?php

namespace Modules\Inventory\Observers;

use Lunar\Models\ProductVariant;
use Modules\Inventory\Services\BackInStockNotifier;

/**
 * Watches variant stock changes: when a variant goes from out-of-stock (≤0) to
 * in-stock (>0), notify shoppers waiting on the back-in-stock list. The
 * decrement pipeline lowers stock (never raises it past 0), so order placement
 * never trips the restock branch.
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
