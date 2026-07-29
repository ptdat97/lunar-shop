<?php

namespace Modules\Notification\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Support\Queues;
use Modules\Notification\Services\OrderSmsNotifier;
use Modules\Order\Events\OrderStatusUpdated;

/**
 * Texts the buyer when an order moves on.
 *
 * Queued and kept separate from {@see SendOrderStatusNotification} on purpose:
 * an SMS is a blocking HTTP call to a third party, and running it inline would
 * put a gateway's latency — or outage — inside the request that changed the
 * order status. Separate listeners also mean a failing SMS cannot stop the push
 * and in-app notification from being delivered.
 */
class SendOrderStatusSms implements ShouldQueue
{
    public string $queue = Queues::NOTIFICATIONS;

    /**
     * A gateway rejecting a number will reject it on every retry, and the
     * driver already swallows transport errors, so retrying buys little; two
     * attempts covers a momentary blip without hammering a provider that is
     * charging per message.
     */
    public int $tries = 2;

    public function __construct(
        protected OrderSmsNotifier $notifier,
    ) {}

    public function handle(OrderStatusUpdated $event): void
    {
        $this->notifier->statusChanged($event->order);
    }
}
