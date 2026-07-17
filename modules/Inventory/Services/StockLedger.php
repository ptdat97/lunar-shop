<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InvalidStockAdjustmentException;
use Modules\Inventory\Models\StockMovement;

/**
 * The single source of truth for the stock ledger.
 *
 * Two responsibilities:
 *  - record(): append one movement for a stock change a caller already made
 *    atomically (sale reservation, order release). The caller passes the exact
 *    before/after and calls this inside its own transaction.
 *  - adjust()/set(): change stock AND record it, atomically, under a row lock —
 *    the admin adjust/bulk paths.
 *
 * INVARIANT: every stock write that feeds the ledger — here and in
 * DecrementStock / StockReleaser — uses the query builder (`whereKey()->update`),
 * never `$sku->save()`. That keeps the Eloquent `updated` event (and the
 * ProductSkuObserver's 'edit' entry) from firing, so a single change is
 * never counted twice. The observer only records changes made through the
 * product/SKU editor, which do use save().
 */
class StockLedger
{
    public function __construct(
        protected BackInStockNotifier $notifier,
    ) {}

    /**
     * Append one movement for a stock change the caller already applied. Runs no
     * stock write of its own — call it inside the caller's transaction.
     */
    public function record(
        int $skuId,
        StockMovementType $type,
        int $delta,
        int $before,
        int $after,
        ?string $reason = null,
        ?Model $causer = null,
        ?int $orderId = null,
        array $meta = [],
    ): StockMovement {
        $movement = new StockMovement([
            'product_sku_id' => $skuId,
            'type' => $type,
            'quantity' => $delta,
            'stock_before' => $before,
            'stock_after' => $after,
            'reason' => $reason,
            'order_id' => $orderId,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);

        if ($causer) {
            $movement->causer()->associate($causer);
        }

        $movement->save();

        return $movement;
    }

    /** Apply a signed delta to a SKU's stock, atomically, and record it. */
    public function adjust(
        int $skuId,
        int $delta,
        StockMovementType $type,
        ?string $reason = null,
        ?Model $causer = null,
        array $meta = [],
    ): StockMovement {
        return $this->mutate($skuId, fn (int $before) => $before + $delta, $type, $reason, $causer, $meta);
    }

    /** Set a SKU's stock to an absolute value, atomically, and record it. */
    public function set(
        int $skuId,
        int $newQuantity,
        StockMovementType $type,
        ?string $reason = null,
        ?Model $causer = null,
        array $meta = [],
    ): StockMovement {
        return $this->mutate($skuId, fn () => $newQuantity, $type, $reason, $causer, $meta);
    }

    /**
     * Lock the SKU, compute the new level, refuse a negative result, write
     * the stock (via query builder, not save()) and the ledger row in one
     * transaction. A restock (0 → positive) fires back-in-stock mail after the
     * commit so it never sends on a rollback.
     *
     * @param  callable(int): int  $resolve  before → after
     */
    protected function mutate(
        int $skuId,
        callable $resolve,
        StockMovementType $type,
        ?string $reason,
        ?Model $causer,
        array $meta,
    ): StockMovement {
        return DB::transaction(function () use ($skuId, $resolve, $type, $reason, $causer, $meta) {
            $sku = ProductSku::whereKey($skuId)->lockForUpdate()->firstOrFail();

            $before = (int) $sku->quantity;
            $after = (int) $resolve($before);

            if ($after < 0) {
                throw new InvalidStockAdjustmentException($skuId, $before, $after - $before);
            }

            ProductSku::whereKey($skuId)->update(['quantity' => $after]);

            $movement = $this->record($skuId, $type, $after - $before, $before, $after, $reason, $causer, null, $meta);

            if ($before <= 0 && $after > 0) {
                DB::afterCommit(fn () => $this->notifier->notify($sku->refresh()));
            }

            return $movement;
        });
    }
}
