<?php

namespace Modules\Notification\Listeners;

use Modules\Notification\Services\OrderNotifier;
use Modules\Order\Events\OrderStatusUpdated;

/**
 * Pushes an order transition to the buyer's app + in-app inbox.
 */
class SendOrderStatusNotification
{
    public function __construct(
        protected OrderNotifier $notifier,
    ) {}

    public function handle(OrderStatusUpdated $event): void
    {
        $this->notifier->statusChanged($event->order, $event->previousStatus);
    }
}
