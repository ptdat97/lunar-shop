<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Modules\Catalog\Models\ProductSku;

/**
 * Settles an order's stock commitment when the goods are dispatched.
 *
 * The commitment model (see {@see StockLedger::commit()}) keeps sold-but-unshipped
 * units on the shelf so `quantity` stays an honest stockroom count. This service
 * is the other half: at dispatch the units leave for real, so `quantity` and
 * `committed` both fall.
 *
 * Idempotent via `lunar_orders.dispatched_at` — mirroring `stock_released_at`.
 * Without it, two writes of the `dispatched` status (a retried webhook, a double
 * click in admin) would decrement the shelf twice for one shipment.
 */
class StockSettler
{
    public function __construct(
        protected StockLedger $ledger,
    ) {}

    /**
     * Take an order's committed units off the shelf. No-op when already settled,
     * or when the order's stock was already released (cancelled before dispatch).
     *
     * @return bool whether this call performed the settlement
     */
    public function settle(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            // Re-read under a lock so two concurrent dispatch writes cannot both
            // see `dispatched_at` as null and settle the same order twice.
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->dispatched_at !== null) {
                return false;
            }

            // Released means cancelled/refunded: the commitment is already gone,
            // so there is nothing to take off the shelf.
            if ($fresh->stock_released_at !== null) {
                return false;
            }

            foreach ($fresh->lines as $line) {
                if ($line->purchasable_type !== (new ProductSku)->getMorphClass()) {
                    continue;
                }

                $quantity = (int) $line->quantity;

                // Same SKU resolution as StockReleaser: ids change when a product
                // edit delete-and-recreates its SKUs, so fall back to the durable
                // sku code carried on the order line.
                $sku = ProductSku::find((int) $line->purchasable_id);

                if (! $sku && filled($line->identifier)) {
                    $sku = ProductSku::where('sku', $line->identifier)->first();
                }

                if (! $sku) {
                    Log::warning('StockSettler: could not resolve SKU for order line; commitment not settled.', [
                        'order_id' => $fresh->id,
                        'order_line_id' => $line->id,
                        'purchasable_id' => $line->purchasable_id,
                        'identifier' => $line->identifier,
                        'quantity' => $quantity,
                    ]);

                    continue;
                }

                $this->ledger->settleCommitment($sku->id, $quantity, (int) $fresh->id);
            }

            $fresh->forceFill(['dispatched_at' => now()])->saveQuietly();

            return true;
        });
    }
}
