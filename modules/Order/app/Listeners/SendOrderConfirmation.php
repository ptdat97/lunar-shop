<?php

namespace Modules\Order\Listeners;

use Lunar\Events\PaymentAttemptEvent;
use Lunar\Models\Order;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Order\Services\OrderMailer;

/**
 * Order confirmation email. Hooks Lunar's PaymentAttemptEvent, fired by every
 * payment driver on authorize (cod/bank + our VNPay driver), so confirmation
 * goes out the moment the order is placed — regardless of payment method.
 */
class SendOrderConfirmation
{
    public function __construct(
        protected OrderMailer $mailer,
    ) {}

    public function handle(PaymentAttemptEvent $event): void
    {
        $auth = $event->paymentAuthorize;

        if (! $auth->success || ! $auth->orderId) {
            return;
        }

        $order = Order::find($auth->orderId);

        if ($order) {
            $this->mailer->send($order, new OrderConfirmationMail($order));
        }
    }
}
