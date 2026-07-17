<?php

namespace Modules\Inventory\Pipelines;

use Closure;
use Illuminate\Support\Facades\DB;
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
     * concurrency. The conditional UPDATE is the lock: if another request
     * already took the last units the affected-rows count is 0 and we raise. A
     * ledger `sale` entry is written on success, inside the order-creation
     * transaction that runs this pipeline.
     */
    protected function reserve(int $skuId, int $quantity, string $description, int $orderId): void
    {
        $sku = ProductSku::findOrFail($skuId);

        $affected = ProductSku::whereKey($skuId)
            ->where('quantity', '>=', $quantity)
            ->update(['quantity' => DB::raw("quantity - {$quantity}")]);

        if ($affected === 0) {
            throw new InsufficientStockException(
                variantId: $skuId,
                requested: $quantity,
                available: (int) $sku->fresh()->quantity,
                description: $description,
            );
        }

        $this->recordSale($skuId, $quantity, $orderId, $description);
    }

    /**
     * Append a `sale` ledger entry. Reads the post-decrement quantity back (the
     * row was already written above in this same transaction) and derives before
     * from the known delta, so before/after stay consistent without a second
     * lock.
     */
    protected function recordSale(int $skuId, int $quantity, int $orderId, string $description): void
    {
        $after = (int) ProductSku::whereKey($skuId)->value('quantity');

        app(StockLedger::class)->record(
            skuId: $skuId,
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
