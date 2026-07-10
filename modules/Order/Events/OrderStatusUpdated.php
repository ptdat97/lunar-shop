<?php

namespace Modules\Order\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

/**
 * An order moved to a new status. Lunar fires no such event, so the Order
 * module raises its own from the model observer.
 *
 * Added because a second consumer appeared (the Notification module needs to
 * tell the mobile app), not "in case" — the same threshold `OrderPaid` met.
 * The status-update *email* stays in the observer: it has its own skip rules and
 * predates this event.
 */
class OrderStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $previousStatus,
    ) {}
}
