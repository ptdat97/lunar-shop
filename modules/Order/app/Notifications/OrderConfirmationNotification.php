<?php

namespace Modules\Order\Notifications;

use Illuminate\Mail\Mailable;
use Modules\Order\Mail\OrderConfirmationMail;

/** Resend of the order confirmation the customer got when they checked out. */
class OrderConfirmationNotification extends ResendableOrderMail
{
    protected function mailable(): Mailable
    {
        return new OrderConfirmationMail($this->order);
    }
}
