<?php

namespace Modules\Inventory\Pipelines;

use Closure;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Enums\StockMovementType;
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
     * Atomically decrement a SKU's stock, guarding against overselling under
     * concurrency, and record a faithful `sale` ledger entry.
     *
     * The row is SELECT … FOR UPDATE first, so `before` is the exact pre-sale
     * level and `after = before - quantity` is derived — not re-read in a second
     * query that a concurrent order could have changed in between (which made the
     * ledger's before/after describe a state that never existed). The lock also
     * serialises the oversell check, the write and the ledger row into one unit.
     * All of this runs inside the order-creation transaction.
     */
    protected function reserve(int $skuId, int $quantity, string $description, int $orderId): void
    {
        $before = ProductSku::whereKey($skuId)->lockForUpdate()->value('quantity');

        if ($before === null) {
            throw new InsufficientStockException(
                variantId: $skuId,
                requested: $quantity,
                available: 0,
                description: $description,
            );
        }

        $before = (int) $before;

        if ($quantity > $before) {
            throw new InsufficientStockException(
                variantId: $skuId,
                requested: $quantity,
                available: $before,
                description: $description,
            );
        }

        $after = $before - $quantity;

        ProductSku::whereKey($skuId)->update(['quantity' => $after]);

        app(StockLedger::class)->record(
            skuId: $skuId,
            type: StockMovementType::Sale,
            delta: -$quantity,
            before: $before,
            after: $after,
            causer: null,
            orderId: $orderId,
            meta: ['line' => $description],
        );
    }
}
