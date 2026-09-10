<?php

namespace Modules\Order\Notifications;

use Illuminate\Mail\Mailable;
use Modules\Order\Mail\OrderPaidMail;

/** Resend of the payment-received email, invoice attachment included. */
class OrderPaidNotification extends ResendableOrderMail
{
    protected function mailable(): Mailable
    {
        return new OrderPaidMail($this->order);
    }
}
