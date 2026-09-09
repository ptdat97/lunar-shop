<?php

namespace Modules\Order\Listeners;

use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Services\OrderTimeline;
use Modules\Order\Support\OrderStatus;

/**
 * Writes an order's status history into the activity log.
 *
 * Lunar 1.x wrote a `status-update` entry itself, from the observer that watched
 * `orders.status`, and {@see OrderTimeline} has always
 * read those entries rather than duplicating them in a table of its own. 2.0
 * removed the column and with it the entry — the core has no headline status to
 * log any more.
 *
 * The shop still has one (derived), so it records its own history in the same
 * place and the same shape. Not a new table for the same reason as before: the
 * activity log already exists and already holds the order's other changes.
 */
class RecordOrderStatusHistory
{
    public function handle(OrderStatusUpdated $event): void
    {
        $new = OrderStatus::of($event->order);

        if ($new === null) {
            return;
        }

        activity()
            ->useLog('lunar')
            ->performedOn($event->order)
            ->event('status-update')
            ->withProperties([
                'previous' => $event->previousStatus ?: null,
                'new' => $new,
            ])
            ->log('status-update');
    }
}
