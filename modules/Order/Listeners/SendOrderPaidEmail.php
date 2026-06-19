<?php

namespace Modules\Order\Listeners;

use Modules\Order\Events\OrderPaid;
use Modules\Order\Mail\OrderPaidMail;
use Modules\Order\Services\OrderMailer;

/**
 * Payment-received email, fired from our OrderPaid domain event (VNPay callback).
 */
class SendOrderPaidEmail
{
    public function __construct(
        protected OrderMailer $mailer,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->mailer->send($event->order, new OrderPaidMail($event->order));
    }
}
