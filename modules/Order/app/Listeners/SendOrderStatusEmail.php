<?php

namespace Modules\Order\Listeners;

use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Mail\OrderStatusUpdatedMail;
use Modules\Order\Services\OrderMailer;
use Modules\Order\Support\OrderStatus;

/**
 * Tells the customer their order moved on.
 *
 * This used to live in the order observer, alongside raising the domain event.
 * Lunar 2.0 made that impossible: the lifecycle is derived from rollups the core
 * writes with `saveQuietly()`, so an observer never sees the transitions that
 * matter. The domain event is now raised from Lunar's own lifecycle events
 * ({@see RaiseOrderStatusUpdated}), and the email is simply one of its
 * consumers — which is what it always should have been.
 *
 * The skip list stays: the payment statuses already have dedicated emails (the
 * order confirmation and the payment-received receipt), so a status-update mail
 * on top of them would be a second message about the same thing.
 */
class SendOrderStatusEmail
{
    /** Statuses handled by other emails. */
    protected const SKIP = [
        OrderStatus::AWAITING_PAYMENT,
        OrderStatus::PAYMENT_OFFLINE,
        OrderStatus::PAYMENT_RECEIVED,
    ];

    public function __construct(
        protected OrderMailer $mailer,
    ) {}

    public function handle(OrderStatusUpdated $event): void
    {
        if (in_array(OrderStatus::of($event->order), self::SKIP, true)) {
            return;
        }

        $this->mailer->send(
            $event->order,
            new OrderStatusUpdatedMail($event->order, $event->previousStatus),
        );
    }
}
