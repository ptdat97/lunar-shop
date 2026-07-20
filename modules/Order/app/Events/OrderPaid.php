<?php

namespace Modules\Order\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

/**
 * Fired when an order's payment is confirmed (e.g. VNPay callback). Lunar has
 * no built-in "order paid" event, so this is our domain signal for the
 * payment-received email + any future fulfilment hooks.
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
