<?php

namespace Modules\Inventory\Listeners;

use Modules\Inventory\Services\StockSettler;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Support\OrderStatus;

/**
 * Takes committed units off the shelf when an order is dispatched.
 *
 * Placing an order only *commits* stock (DecrementStock → StockLedger::commit):
 * the goods are still in the stockroom, just no longer sellable. Dispatch is the
 * moment they physically leave, so that is where `quantity` finally falls.
 */
class SettleStockOnDispatch
{
    public function __construct(
        protected StockSettler $settler,
    ) {}

    /**
     * Deliberately synchronous, for the same reason as
     * {@see ReleaseStockOnOrderClosed}: stock is a correctness invariant, not a
     * notification. A dead queue must not leave the shelf count overstated.
     */
    public function handle(OrderStatusUpdated $event): void
    {
        if ($event->order->status !== OrderStatus::DISPATCHED) {
            return;
        }

        $this->settler->settle($event->order);
    }
}
