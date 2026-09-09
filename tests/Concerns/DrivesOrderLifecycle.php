<?php

namespace Tests\Concerns;

use Illuminate\Support\Carbon;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\Transaction;
use Modules\Order\Support\OrderStatus;

/**
 * Puts an order into one of the shop's seven lifecycle statuses.
 *
 * Lunar 2.0 deleted `lunar_orders.status`; the lifecycle is derived from the
 * transaction ledger, the fulfilment records and the cancelled / closed
 * timestamps ({@see OrderStatus}). So a test can no longer say
 * `Order::factory()->create(['status' => 'dispatched'])` — there is no such
 * column, and inventing one would put the test on a different mechanism than
 * the shop.
 *
 * Two entry points, for the two things tests actually do:
 *
 *   orderAttributesFor()  the columns to hand a factory, for a test that only
 *                         needs an order that READS as a given status.
 *   moveOrderTo()         a real transition on an existing order, for a test
 *                         that needs the side effects — the domain event, the
 *                         stock release, the email. It records the same facts
 *                         production records, so Lunar's own observers fire.
 */
trait DrivesOrderLifecycle
{
    /**
     * Factory attributes for an order that reads as `$status`.
     *
     * @return array<string, mixed>
     */
    protected function orderAttributesFor(string $status, array $meta = []): array
    {
        $base = [
            'placed_at' => now(),
            'payment_status' => 'pending',
            'fulfilment_status' => 'unfulfilled',
            'cancelled_at' => null,
            'closed_at' => null,
            // The gateway/COD distinction the two rollups cannot make: an order
            // with no payment type recorded is paid on delivery.
            'meta' => [...$meta, 'payment_type' => $this->paymentTypeFor($status, $meta)],
        ];

        return match ($status) {
            OrderStatus::AWAITING_PAYMENT => $base,
            OrderStatus::PAYMENT_OFFLINE => $base,
            OrderStatus::PAYMENT_RECEIVED => [...$base, 'payment_status' => 'paid'],
            OrderStatus::DISPATCHED => [...$base, 'payment_status' => 'paid', 'fulfilment_status' => 'fulfilled'],
            OrderStatus::COMPLETED => [...$base, 'payment_status' => 'paid', 'fulfilment_status' => 'fulfilled', 'closed_at' => now()],
            OrderStatus::REFUNDED => [...$base, 'payment_status' => 'refunded'],
            OrderStatus::CANCELLED => [...$base, 'cancelled_at' => now()],
            default => throw new \InvalidArgumentException("Unknown order status: {$status}"),
        };
    }

    /**
     * A payment type consistent with the status, unless the test named one.
     *
     * `awaiting-payment` only exists for gateway orders — a COD order is a sale
     * from the moment it is placed — so it needs a gateway here or the derived
     * status comes back `payment-offline`.
     */
    protected function paymentTypeFor(string $status, array $meta = []): string
    {
        return $meta['payment_type']
            ?? ($status === OrderStatus::AWAITING_PAYMENT ? 'vnpay' : 'cod');
    }

    /**
     * Move an existing order to `$status` by recording the fact that causes it,
     * so Lunar's observers recompute the rollups and the shop's
     * `OrderStatusUpdated` fires exactly as it would in production.
     */
    protected function moveOrderTo(Order $order, string $status): Order
    {
        match ($status) {
            OrderStatus::PAYMENT_RECEIVED => $this->captureOrder($order),
            OrderStatus::DISPATCHED => $this->fulfilOrder($order),
            OrderStatus::COMPLETED => $order->close(),
            OrderStatus::CANCELLED => $order->cancel(reason: 'test', notify: false),
            OrderStatus::REFUNDED => $this->refundOrder($order),
            default => throw new \InvalidArgumentException("Cannot move an order to: {$status}"),
        };

        return $order->refresh();
    }

    /** A successful capture for the full total — what a gateway callback records. */
    protected function captureOrder(Order $order): void
    {
        Transaction::create([
            'order_id' => $order->id,
            'success' => true,
            'type' => 'capture',
            'driver' => 'offline',
            'amount' => (int) $order->total,
            'reference' => 'test-capture-'.$order->id,
            'status' => 'captured',
            'card_type' => '',
            'last_four' => '',
            'captured_at' => Carbon::now(),
        ]);
    }

    /** A refund covering everything captured. */
    protected function refundOrder(Order $order): void
    {
        $captured = (int) $order->captures()->whereSuccess(true)->sum('amount');

        if ($captured === 0) {
            $this->captureOrder($order);
            $captured = (int) $order->total;
        }

        Transaction::create([
            'order_id' => $order->id,
            'success' => true,
            'type' => 'refund',
            'driver' => 'offline',
            'amount' => $captured,
            'reference' => 'test-refund-'.$order->id,
            'status' => 'refunded',
            'card_type' => '',
            'last_four' => '',
            'captured_at' => Carbon::now(),
        ]);
    }

    /** One whole-order fulfilment covering every fulfillable line in full. */
    protected function fulfilOrder(Order $order): void
    {
        $lines = $order->fulfillableLines()->get()
            ->mapWithKeys(fn ($line) => [$line->id => $line->quantity])
            ->all();

        // Nothing physical to send (a digital or shipping-only order) already
        // rolls up as fulfilled; forcing a fulfilment with no lines would throw.
        if ($lines === []) {
            return;
        }

        $order->createFulfilment($lines);
    }
}
