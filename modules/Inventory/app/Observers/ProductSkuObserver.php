<?php

namespace Modules\Inventory\Observers;

use Filament\Facades\Filament;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Services\BackInStockNotifier;
use Modules\Inventory\Services\StockLedger;

/**
 * Watches SKU stock changes made through Eloquent `save()` — the product / SKU
 * editors. Two jobs when `quantity` changes:
 *
 *  1. Record an `edit` ledger entry (attributed to the current staff), so
 *     editor changes appear in the stock history alongside sales/adjustments.
 *  2. When a SKU goes out-of-stock (≤0) → in-stock (>0), notify shoppers on the
 *     back-in-stock list.
 *
 * The sale/release/adjust paths write stock via the query builder (not save()),
 * so they never reach this observer — they record their own ledger entries.
 * That is what keeps a single change from being logged twice.
 */
class ProductSkuObserver
{
    public function __construct(
        protected BackInStockNotifier $notifier,
        protected StockLedger $ledger,
    ) {}

    public function updated(ProductSku $sku): void
    {
        if (! $sku->wasChanged('quantity')) {
            return;
        }

        $previous = (int) $sku->getOriginal('quantity');
        $current = (int) $sku->quantity;

        $this->ledger->record(
            skuId: $sku->id,
            type: StockMovementType::Edit,
            delta: $current - $previous,
            before: $previous,
            after: $current,
            causer: Filament::auth()->user(),
        );

        if ($previous <= 0 && $current > 0) {
            $this->notifier->notify($sku);
        }
    }
}
