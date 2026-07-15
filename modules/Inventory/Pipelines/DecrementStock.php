<?php

namespace Modules\Inventory\Pipelines;

use Closure;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InsufficientStockException;
use Modules\Inventory\Services\StockLedger;

/**
 * Reserves stock when an order is created: for each order line backed by a
 * product variant, atomically decrement `stock` by the ordered quantity.
 *
 * Oversell guard: variants set to `purchasable = in_stock` (the default) cannot
 * be decremented below the available inventory — a concurrent checkout that
 * would oversell throws InsufficientStockException, rolling back order creation.
 * Variants set to `backorder`/`always` are allowed to go negative (Lunar's
 * own semantics — backorders are intentional).
 *
 * This is appended to lunar.orders.pipelines.creation (see Config/overrides.php),
 * Lunar's official order-creation extension point — no vendor code is touched.
 */
class DecrementStock
{
    /**
     * @param  Closure(OrderContract): mixed  $next
     */
    public function handle(OrderContract $order, Closure $next): mixed
    {
        /** @var Order $order */
        $order->loadMissing('lines');

        foreach ($order->lines as $line) {
            if ($line->purchasable_type !== (new ProductVariant)->getMorphClass()) {
                continue;
            }

            $this->reserve(
                (int) $line->purchasable_id,
                (int) $line->quantity,
                (string) $line->description,
                (int) $order->id,
            );
        }

        return $next($order);
    }

    /**
     * Atomically decrement a variant's stock, guarding against overselling
     * `in_stock` variants under concurrency. The conditional UPDATE is the lock:
     * if another request already took the last units the affected-rows count is
     * 0 and we raise — no read-then-write race. A ledger `sale` entry is written
     * on success, inside the order-creation transaction that runs this pipeline.
     */
    protected function reserve(int $variantId, int $quantity, string $description, int $orderId): void
    {
        $variant = ProductVariant::findOrFail($variantId);

        // Backorder/always variants may go negative — Lunar treats this as
        // intentional, so just decrement unconditionally.
        if ($variant->purchasable !== 'in_stock') {
            ProductVariant::whereKey($variantId)->update([
                'stock' => DB::raw("stock - {$quantity}"),
            ]);

            $this->recordSale($variantId, $quantity, $orderId, $description);

            return;
        }

        $affected = ProductVariant::whereKey($variantId)
            ->where('stock', '>=', $quantity)
            ->update(['stock' => DB::raw("stock - {$quantity}")]);

        if ($affected === 0) {
            throw new InsufficientStockException(
                variantId: $variantId,
                requested: $quantity,
                available: (int) $variant->fresh()->stock,
                description: $description,
            );
        }

        $this->recordSale($variantId, $quantity, $orderId, $description);
    }

    /**
     * Append a `sale` ledger entry. Reads the post-decrement stock back (the row
     * was already written above in this same transaction) and derives before
     * from the known delta, so before/after stay consistent without a second
     * lock.
     */
    protected function recordSale(int $variantId, int $quantity, int $orderId, string $description): void
    {
        $after = (int) ProductVariant::whereKey($variantId)->value('stock');

        app(StockLedger::class)->record(
            variantId: $variantId,
            type: StockMovementType::Sale,
            delta: -$quantity,
            before: $after + $quantity,
            after: $after,
            causer: null,
            orderId: $orderId,
            meta: ['line' => $description],
        );
    }
}
