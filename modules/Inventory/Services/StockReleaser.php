<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Pipelines\DecrementStock;

/**
 * Puts an order's reserved stock back.
 *
 * {@see DecrementStock} reserves inventory when the
 * order row is created. For a gateway payment that happens *before* the customer
 * pays, so an abandoned VNPay checkout held the units forever; nothing released
 * them on cancellation or refund either.
 *
 * Idempotent by design: `stock_released_at` is written in the same transaction
 * as the increments, so a retry, a double-click or two workers racing cannot
 * conjure inventory that was never sold.
 */
class StockReleaser
{
    public function __construct(
        protected StockLedger $ledger,
    ) {}

    /**
     * Return an order's units to stock. No-op when already released.
     *
     * @return bool whether this call performed the release
     */
    public function release(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            // Re-read under a lock: two callers (a cancel and a refund arriving
            // together) must not both see `stock_released_at` as null.
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->stock_released_at !== null) {
                return false;
            }

            foreach ($fresh->lines as $line) {
                if ($line->purchasable_type !== (new ProductVariant)->getMorphClass()) {
                    continue;
                }

                $variantId = (int) $line->purchasable_id;
                $quantity = (int) $line->quantity;

                $before = (int) ProductVariant::whereKey($variantId)->value('stock');

                ProductVariant::whereKey($variantId)->update([
                    'stock' => DB::raw('stock + '.$quantity),
                ]);

                // Ledger `release` entry, inside this release transaction so a
                // rollback drops it too. System-caused (cancel/refund/CLI).
                $this->ledger->record(
                    variantId: $variantId,
                    type: StockMovementType::Release,
                    delta: $quantity,
                    before: $before,
                    after: $before + $quantity,
                    causer: null,
                    orderId: (int) $fresh->id,
                    meta: ['status' => (string) $fresh->status],
                );
            }

            $fresh->forceFill(['stock_released_at' => now()])->saveQuietly();

            return true;
        });
    }
}
