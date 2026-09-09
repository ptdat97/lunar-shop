<?php

namespace Modules\Order\Observers;

use Lunar\Core\Models\Order;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Mail\OrderStatusUpdatedMail;
use Modules\Order\Services\OrderMailer;
use Modules\Order\Support\OrderStatus;

/**
 * Watches an order's lifecycle and raises the shop's own domain event.
 *
 * Lunar 2.0 has no headline `status` column to watch — the lifecycle is derived
 * from four facts ({@see OrderStatus}) that different parts of the core write
 * independently: the transaction observer recomputes `payment_status`, the
 * fulfilment observers recompute `fulfilment_status`, and `cancel()` / `close()`
 * stamp their timestamps. So instead of `wasChanged('status')` this compares the
 * status DERIVED from the row before the save with the one derived after, and
 * fires only when they actually differ.
 *
 * That is strictly better than the old column watch: it cannot fire for a write
 * that renamed nothing, and it cannot miss a transition that arrived through a
 * different column than the one being watched.
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

    /**
     * The columns the derived status is built from. A save that touches none of
     * them cannot have changed it, so the comparison is skipped entirely.
     *
     * @var list<string>
     */
    protected const DERIVED_FROM = [
        'payment_status',
        'fulfilment_status',
        'cancelled_at',
        'closed_at',
        'placed_at',
        'meta',
    ];

    public function __construct(
        protected OrderMailer $mailer,
    ) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged(self::DERIVED_FROM)) {
            return;
        }

        $current = OrderStatus::of($order);
        $previous = OrderStatus::of($this->asItWas($order));

        if ($previous === $current) {
            return;
        }

        OrderStatusUpdated::dispatch($order, (string) $previous);

        if (in_array($current, self::SKIP_MAIL, true)) {
            return;
        }

        $this->mailer->send($order, new OrderStatusUpdatedMail($order, (string) $previous));
    }

    /**
     * The order as it was before this save, for deriving the previous status.
     *
     * A detached copy rather than a mutation of `$order`: the real instance is
     * about to be handed to listeners and to the mail, and they must see the new
     * state.
     *
     * `getRawOriginal()`, not `getOriginal()` — the latter runs the values back
     * through the casts, so `payment_status` comes out as a State OBJECT, and
     * feeding that to `setRawAttributes()` leaves the copy holding an object
     * where the cast expects the column string. Reading it then dies inside
     * spatie's StateCaster with "Cannot access offset of type Pending on array".
     */
    protected function asItWas(Order $order): Order
    {
        $before = $order->newInstance([], exists: true);
        $before->setRawAttributes($order->getRawOriginal(), sync: true);

        return $before;
    }
}
