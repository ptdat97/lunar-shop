<?php

namespace Modules\Inventory\Observers;

use Filament\Facades\Filament;
use Lunar\Models\ProductVariant;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Services\BackInStockNotifier;
use Modules\Inventory\Services\StockLedger;

/**
 * Watches variant stock changes made through Eloquent `save()` — the product /
 * variant editors. Two jobs when `stock` changes:
 *
 *  1. Record an `edit` ledger entry (attributed to the current staff), so
 *     editor changes appear in the stock history alongside sales/adjustments.
 *  2. When a variant goes out-of-stock (≤0) → in-stock (>0), notify shoppers on
 *     the back-in-stock list.
 *
 * The sale/release/adjust paths write stock via the query builder (not save()),
 * so they never reach this observer — they record their own ledger entries.
 * That is what keeps a single change from being logged twice.
 */
class ProductVariantObserver
{
    public function __construct(
        protected BackInStockNotifier $notifier,
        protected StockLedger $ledger,
    ) {}

    public function updated(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('stock')) {
            return;
        }

        $previous = (int) $variant->getOriginal('stock');
        $current = (int) $variant->stock;

        $this->ledger->record(
            variantId: $variant->id,
            type: StockMovementType::Edit,
            delta: $current - $previous,
            before: $previous,
            after: $current,
            causer: Filament::auth()->user(),
        );

        if ($previous <= 0 && $current > 0) {
            $this->notifier->notify($variant);
        }
    }
}
