<?php

namespace Modules\Inventory\Listeners;

use Lunar\Core\Events\Orders\OrderCancelled;

/**
 * Records WHEN an order's held stock went back.
 *
 * Lunar releases the stock itself (`OrderCancelled` → `SyncStockForOrder`
 * recomputes the commitment away), but it keeps no timestamp for it. The shop
 * does, and two things read it: the stale-commitment warning skips orders whose
 * hold is already accounted for, and `orders:expire-abandoned` uses it to avoid
 * sweeping the same order twice.
 *
 * Idempotent — a second cancel leaves the first timestamp alone, because "when
 * did this stock come back" has one answer.
 */
class StampStockReleased
{
    public function handle(OrderCancelled $event): void
    {
        if ($event->order->stock_released_at) {
            return;
        }

        $event->order->forceFill(['stock_released_at' => now()])->saveQuietly();
    }
}
