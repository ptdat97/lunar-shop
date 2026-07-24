<?php

namespace Modules\Inventory\Pipelines;

use Closure;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Exceptions\InsufficientStockException;
use Modules\Inventory\Services\StockLedger;

/**
 * Reserves stock when an order is created: for each order line backed by a
 * SKU, atomically decrement `quantity` by the ordered amount.
 *
 * Oversell guard: a SKU cannot be decremented below its available inventory — a
 * concurrent checkout that would oversell throws InsufficientStockException,
 * rolling back order creation. The conditional UPDATE is the lock, so there is
 * no read-then-write race.
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
            if ($line->purchasable_type !== (new ProductSku)->getMorphClass()) {
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
     * Hold the units for this order.
     *
     * Placing an order no longer takes stock off the shelf — it *commits* it.
     * `quantity` keeps answering "how many are in the stockroom" and `committed`
     * answers "how many of those are already sold", so the shop can tell the two
     * apart. The units leave `quantity` when the order is dispatched
     * (StockLedger::settleCommitment), or return to the sellable pool when it is
     * cancelled (StockLedger::uncommit).
     *
     * StockLedger::commit() locks the row, so the oversell check, the write and
     * the ledger entry are one unit — no read-then-write race. All of it runs
     * inside the order-creation transaction, so a later failure rolls the hold
     * back with the order.
     */
    protected function reserve(int $skuId, int $quantity, string $description, int $orderId): void
    {
        app(StockLedger::class)->commit($skuId, $quantity, $description, $orderId);
    }
}
