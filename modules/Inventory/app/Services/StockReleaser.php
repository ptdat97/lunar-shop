<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Modules\Catalog\Models\ProductSku;
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
                if ($line->purchasable_type !== (new ProductSku)->getMorphClass()) {
                    continue;
                }

                $quantity = (int) $line->quantity;

                // Resolve the SKU to credit. Prefer the recorded id, but a product
                // edit delete-and-recreates SKUs (ids change), so the id can point
                // at a since-deleted row. Fall back to the CURRENT SKU carrying the
                // same durable code (order_lines.identifier = getIdentifier() = the
                // sku string). Without this fallback the units are lost forever.
                $sku = ProductSku::find((int) $line->purchasable_id);

                if (! $sku && filled($line->identifier)) {
                    $sku = ProductSku::where('sku', $line->identifier)->first();
                }

                if (! $sku) {
                    // The variant no longer exists under any code — nothing to
                    // credit (a ledger row can't be written either: its FK targets
                    // an existing SKU). Log it so the un-restocked units are
                    // auditable rather than vanishing silently.
                    Log::warning('StockReleaser: could not resolve SKU for order line; stock not returned.', [
                        'order_id' => $fresh->id,
                        'order_line_id' => $line->id,
                        'purchasable_id' => $line->purchasable_id,
                        'identifier' => $line->identifier,
                        'quantity' => $quantity,
                    ]);

                    continue;
                }

                // Which figure to credit depends on whether the units ever left
                // the shelf. An order cancelled BEFORE dispatch only ever held a
                // commitment — `quantity` never moved, so adding to it here would
                // invent stock. An order cancelled AFTER dispatch (a return) did
                // leave, so the units genuinely come back on the shelf.
                if ($fresh->dispatched_at === null) {
                    $this->ledger->uncommit($sku->id, $quantity, (int) $fresh->id);

                    continue;
                }

                $before = (int) ProductSku::whereKey($sku->id)->value('quantity');

                ProductSku::whereKey($sku->id)->update([
                    'quantity' => DB::raw('quantity + '.$quantity),
                ]);

                // Ledger `release` entry, inside this release transaction so a
                // rollback drops it too. System-caused (cancel/refund/CLI).
                $this->ledger->record(
                    skuId: $sku->id,
                    type: StockMovementType::Release,
                    delta: $quantity,
                    before: $before,
                    after: $before + $quantity,
                    causer: null,
                    orderId: (int) $fresh->id,
                    meta: ['status' => (string) $fresh->status, 'after_dispatch' => true],
                );
            }

            $fresh->forceFill(['stock_released_at' => now()])->saveQuietly();

            return true;
        });
    }
}
