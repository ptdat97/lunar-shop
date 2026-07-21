<?php

namespace Modules\Order\Listeners;

use Modules\Order\Events\OrderPaid;
use Modules\Order\Mail\OrderPaidMail;
use Modules\Order\Services\OrderMailer;
use Modules\Order\Support\OrderStatus;

/**
 * Payment-received email, fired from our OrderPaid domain event.
 *
 * OrderPaid means "counts as paid" for spend/analytics, which includes COD
 * (`payment-offline`) — but a COD customer hands over money on delivery, not at
 * checkout. This mail says "Amount paid", so it must only go out when a gateway
 * actually captured funds.
 */
class SendOrderPaidEmail
{
    /** Statuses where money has genuinely been captured. */
    protected const CAPTURED = [OrderStatus::PAYMENT_RECEIVED];

    public function __construct(
        protected OrderMailer $mailer,
    ) {}

    public function handle(OrderPaid $event): void
    {
        if (! in_array($event->order->status, self::CAPTURED, true)) {
            return;
        }

        $this->mailer->send($event->order, new OrderPaidMail($event->order));
    }
}
