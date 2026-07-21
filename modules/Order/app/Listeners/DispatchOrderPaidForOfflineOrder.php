<?php

namespace Modules\Order\Listeners;

use Lunar\Events\PaymentAttemptEvent;
use Lunar\Models\Order;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Support\OrderStatus;

/**
 * Raises OrderPaid for offline orders that are already "paid" the moment they
 * are placed — i.e. COD, whose authorize() status (`payment-offline`) counts as
 * realised revenue.
 *
 * Gateways (VNPay/MoMo) authorize to `awaiting-payment` and dispatch OrderPaid
 * themselves from their capture callback, so gating on the paid statuses means
 * this listener fires for COD only — never a double dispatch. Bank transfer
 * authorizes to `awaiting-payment` too and is correctly skipped until staff
 * confirm the transfer.
 */
class DispatchOrderPaidForOfflineOrder
{
    public function handle(PaymentAttemptEvent $event): void
    {
        $auth = $event->paymentAuthorize;

        if (! $auth->success || ! $auth->orderId) {
            return;
        }

        $order = Order::find($auth->orderId);

        // The single definition of "counts as paid", shared with the sales
        // dashboard, co-purchase recommendations and fit history.
        if (! $order || ! OrderStatus::isPaid($order->status)) {
            return;
        }

        OrderPaid::dispatch($order);
    }
}
