<?php

namespace Modules\Inventory\Listeners;

use Modules\Inventory\Services\StockReleaser;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Support\OrderStatus;

/**
 * Returns reserved stock when an order will never be fulfilled.
 *
 * Stock is reserved at order creation. Until now nothing gave it back, so a
 * cancelled or refunded order quietly destroyed inventory.
 */
class ReleaseStockOnOrderClosed
{
    public function __construct(
        protected StockReleaser $releaser,
    ) {}

    /**
     * Deliberately synchronous. Stock is a correctness invariant, not a
     * notification: if the queue were down the units would stay lost and
     * nothing would say so.
     */
    public function handle(OrderStatusUpdated $event): void
    {
        if (! in_array($event->order->status, OrderStatus::CLOSED, true)) {
            return;
        }

        $this->releaser->release($event->order);
    }
}
