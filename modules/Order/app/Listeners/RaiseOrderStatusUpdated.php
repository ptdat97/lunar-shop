<?php

namespace Modules\Order\Listeners;

use Lunar\Core\Events\Orders\OrderCancelled;
use Lunar\Core\Events\Orders\OrderClosed;
use Lunar\Core\Events\Orders\OrderFulfilmentStatusUpdated;
use Lunar\Core\Events\Orders\OrderPaymentStatusUpdated;
use Lunar\Core\Events\Orders\OrderReopened;
use Lunar\Core\Models\Order;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Support\OrderStatus;

/**
 * Turns Lunar 2.0's four lifecycle events into the shop's own
 * {@see OrderStatusUpdated}.
 *
 * This has to be an event listener rather than a model observer. 2.0 recomputes
 * `payment_status` / `fulfilment_status` with `saveQuietly()` — deliberately, so
 * the rollups never loop back through the order observer — so an observer would
 * simply never see the two transitions that matter most. The events are the only
 * place those changes surface.
 *
 * The shop's status is derived from four facts at once, so "what was it before?"
 * cannot be read off the event alone: each handler rebuilds the order as it was
 * for its own fact and derives from that. When a change does not move the
 * derived status — a partial refund, say, which leaves a paid order paid — no
 * event is raised, which is the whole point of comparing derived values instead
 * of watching a column.
 */
class RaiseOrderStatusUpdated
{
    public function handlePaymentStatus(OrderPaymentStatusUpdated $event): void
    {
        $this->raise($event->order, ['payment_status' => $event->previousStatus?->getValue()]);
    }

    public function handleFulfilmentStatus(OrderFulfilmentStatusUpdated $event): void
    {
        $this->raise($event->order, ['fulfilment_status' => $event->previousStatus?->getValue()]);
    }

    public function handleCancelled(OrderCancelled $event): void
    {
        $this->raise($event->order, ['cancelled_at' => null, 'closed_at' => null]);
    }

    public function handleClosed(OrderClosed $event): void
    {
        $this->raise($event->order, ['closed_at' => null]);
    }

    public function handleReopened(OrderReopened $event): void
    {
        // Reopening restores an order to whatever its rollups say; before it,
        // the order read as closed.
        $this->raise($event->order, ['closed_at' => $event->order->freshTimestampString()]);
    }

    /**
     * Raise the domain event if the given overrides describe a different
     * lifecycle status than the order now has.
     *
     * @param  array<string, mixed>  $previousAttributes  raw column values as they were
     */
    protected function raise(Order $order, array $previousAttributes): void
    {
        $current = OrderStatus::of($order);
        $previous = OrderStatus::of($this->asItWas($order, $previousAttributes));

        if ($previous === $current) {
            return;
        }

        OrderStatusUpdated::dispatch($order, (string) $previous);
    }

    /**
     * A detached copy of the order carrying the given raw column values.
     *
     * Detached rather than a mutation of the real instance: that one is about to
     * be handed to listeners and to the customer's email, and they must see the
     * order as it now is.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function asItWas(Order $order, array $overrides): Order
    {
        $before = $order->newInstance([], exists: true);
        $before->setRawAttributes([...$order->getRawOriginal(), ...$overrides], sync: true);

        return $before;
    }
}
