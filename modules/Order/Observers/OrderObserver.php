<?php

namespace Modules\Order\Observers;

use Lunar\Models\Order;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Mail\OrderStatusUpdatedMail;
use Modules\Order\Services\OrderMailer;
use Modules\Order\Support\OrderStatus;

/**
 * Watches an order's status. Lunar fires no order-status event, so we observe
 * the model's dirty `status` and raise our own domain event from here.
 *
 * The status-update *email* keeps its skip list: payment statuses already have
 * dedicated emails (confirmation / payment-received) and must not double-notify.
 * The domain event has no such exclusion — an app has no other channel, so it
 * should hear about every transition.
 */
class OrderObserver
{
    /** Statuses handled by other emails (no status-update mail for these). */
    protected const SKIP_MAIL = [
        OrderStatus::AWAITING_PAYMENT,
        OrderStatus::PAYMENT_OFFLINE,
        OrderStatus::PAYMENT_RECEIVED,
    ];

    public function __construct(
        protected OrderMailer $mailer,
    ) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $previous = (string) $order->getOriginal('status');

        OrderStatusUpdated::dispatch($order, $previous);

        if (in_array($order->status, self::SKIP_MAIL, true)) {
            return;
        }

        $this->mailer->send($order, new OrderStatusUpdatedMail($order, $previous));
    }
}
