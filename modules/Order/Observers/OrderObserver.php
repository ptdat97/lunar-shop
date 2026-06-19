<?php

namespace Modules\Order\Observers;

use Lunar\Models\Order;
use Modules\Order\Mail\OrderStatusUpdatedMail;
use Modules\Order\Services\OrderMailer;

/**
 * Emails the customer when an order's status changes (e.g. via Filament admin).
 * Lunar fires no order-status event, so we observe the model's `status` dirty
 * state. Payment statuses are skipped — they have dedicated emails
 * (confirmation / payment-received) and shouldn't double-notify.
 */
class OrderObserver
{
    /** Statuses handled by other emails (no status-update mail for these). */
    protected const SKIP = ['awaiting-payment', 'payment-offline', 'payment-received'];

    public function __construct(
        protected OrderMailer $mailer,
    ) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $previous = $order->getOriginal('status');

        if (in_array($order->status, self::SKIP, true)) {
            return;
        }

        $this->mailer->send($order, new OrderStatusUpdatedMail($order, (string) $previous));
    }
}
