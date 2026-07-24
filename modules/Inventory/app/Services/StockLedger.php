<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InsufficientStockException;
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
     * Reserve units for an order: hold them without taking them off the shelf.
     *
     * `quantity` (what is physically in the stockroom) is untouched; `committed`
     * rises so the units stop being sellable. Settled later by settleCommitment()
     * on dispatch, or handed back by uncommit() on cancel/refund.
     *
     * Runs inside the caller's transaction (order creation) and locks the row, so
     * the oversell check and the write are one unit.
     *
     * @throws InsufficientStockException when fewer units are sellable than asked
     */
    public function commit(int $skuId, int $quantity, string $description, int $orderId): void
    {
        $sku = ProductSku::whereKey($skuId)->lockForUpdate()->first();

        $onHand = (int) ($sku->quantity ?? 0);
        $committed = (int) ($sku->committed ?? 0);
        $sellable = $onHand - $committed;

        if (! $sku || $quantity > $sellable) {
            throw new InsufficientStockException(
                variantId: $skuId,
                requested: $quantity,
                available: max($sellable, 0),
                description: $description,
            );
        }

        ProductSku::whereKey($skuId)->update(['committed' => $committed + $quantity]);

        // The shelf count did not move, so before/after both report on-hand — a
        // faithful record of what a stock-take would have counted at the time.
        $this->record(
            skuId: $skuId,
            type: StockMovementType::Sale,
            delta: -$quantity,
            before: $onHand,
            after: $onHand,
            reason: $description,
            orderId: $orderId,
            meta: ['committed_before' => $committed, 'committed_after' => $committed + $quantity],
        );
    }

    /**
     * Hand committed units back without shipping them (cancel / refund /
     * abandoned-order sweep). The shelf count never changed, so only `committed`
     * unwinds — clamped at zero so a double release cannot drive it negative and
     * silently inflate what is sellable.
     */
    public function uncommit(int $skuId, int $quantity, ?int $orderId = null): void
    {
        $sku = ProductSku::whereKey($skuId)->lockForUpdate()->first();

        if (! $sku) {
            return;
        }

        $onHand = (int) $sku->quantity;
        $committed = (int) $sku->committed;
        $returned = min($quantity, $committed);

        ProductSku::whereKey($skuId)->update(['committed' => $committed - $returned]);

        $this->record(
            skuId: $skuId,
            type: StockMovementType::Release,
            delta: $returned,
            before: $onHand,
            after: $onHand,
            reason: 'Trả lại hàng đã giữ',
            orderId: $orderId,
            meta: ['committed_before' => $committed, 'committed_after' => $committed - $returned],
        );
    }

    /**
     * Settle a commitment on dispatch: the units physically leave the shelf.
     *
     * This is the only place `quantity` falls for a sale, so the ledger's
     * before/after finally describe a real change in the stockroom.
     */
    public function settleCommitment(int $skuId, int $quantity, ?int $orderId = null): void
    {
        $sku = ProductSku::whereKey($skuId)->lockForUpdate()->first();

        if (! $sku) {
            return;
        }

        $onHand = (int) $sku->quantity;
        $committed = (int) $sku->committed;

        // Never drive either figure negative: a partially-released order, or a
        // re-dispatch, must not manufacture or destroy units.
        $shipped = min($quantity, $onHand, $committed);

        ProductSku::whereKey($skuId)->update([
            'quantity' => $onHand - $shipped,
            'committed' => $committed - $shipped,
        ]);

        $this->record(
            skuId: $skuId,
            type: StockMovementType::Sale,
            delta: -$shipped,
            before: $onHand,
            after: $onHand - $shipped,
            reason: 'Xuất kho giao hàng',
            orderId: $orderId,
            meta: ['committed_before' => $committed, 'committed_after' => $committed - $shipped],
        );
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

            // Never let the shelf fall below what is already promised. A manual
            // correction to "1 on hand" while 3 units are committed describes a
            // shop that has sold goods it cannot ship — and nothing downstream
            // would flag it, because sellable is clamped at 0 either way. Refuse
            // it: the honest fix is to cancel the orders that cannot be filled.
            $committed = (int) $sku->committed;

            if ($after < $committed) {
                throw new InvalidStockAdjustmentException(
                    variantId: $skuId,
                    current: $before,
                    delta: $after - $before,
                    committed: $committed,
                );
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
