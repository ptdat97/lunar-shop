<?php

namespace Tests\Concerns;

use Illuminate\Support\Carbon;
use Lunar\Core\Actions\Fulfilment\FulfilFulfilment;
use Lunar\Core\Contracts\Actions\Fulfilment\EnsuresInitialFulfilment;
use Lunar\Core\Contracts\Actions\Orders\ResolvesFulfilmentStatus;
use Lunar\Core\Models\Fulfilment;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
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
     * Statuses a fresh order can express through its own columns, with no
     * supporting transaction or fulfilment records.
     *
     * @var list<string>
     */
    protected const COLUMN_EXPRESSIBLE = [
        OrderStatus::AWAITING_PAYMENT,
        OrderStatus::PAYMENT_OFFLINE,
        OrderStatus::PAYMENT_RECEIVED,
        OrderStatus::REFUNDED,
        OrderStatus::CANCELLED,
    ];

    /**
     * Factory attributes for an order that reads as `$status`.
     *
     * Only the statuses a fresh order can express through its own columns.
     * `dispatched` and `completed` are not among them: they need a fulfilment
     * over real lines, which cannot exist before the order does — use
     * {@see self::moveOrderTo()} once it has been created.
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
            OrderStatus::REFUNDED => [...$base, 'payment_status' => 'refunded'],
            OrderStatus::CANCELLED => [...$base, 'cancelled_at' => now()],
            OrderStatus::DISPATCHED, OrderStatus::COMPLETED => throw new \InvalidArgumentException(
                "'{$status}' needs a fulfilment over real order lines, which cannot exist before the order does. "
                .'Create the order first, then call moveOrderTo().'
            ),
            default => throw new \InvalidArgumentException("Unknown order status: {$status}"),
        };
    }

    /**
     * The status a fresh row can be created in on the way to `$status`.
     *
     * Anything the columns can express is created directly; the rest starts at
     * `payment-received`, the step before goods are handed over.
     */
    protected function creatableStatusFor(string $status): string
    {
        return in_array($status, self::COLUMN_EXPRESSIBLE, true)
            ? $status
            : OrderStatus::PAYMENT_RECEIVED;
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
        // Order matters. Give the order something to send BEFORE recording any
        // payment: creating a transaction recomputes the fulfilment rollup, and
        // an order with no fulfillable lines rolls up as "fulfilled" (settled by
        // definition). Adding a line afterwards would flip it back and produce a
        // status change that never happened.
        if (in_array($status, [OrderStatus::DISPATCHED, OrderStatus::COMPLETED], true)) {
            $this->ensureFulfillableLine($order);
        }

        $this->reconcilePaymentLedger($order);

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

    /**
     * Make the order's claimed payment status true.
     *
     * `orderAttributesFor()` writes `payment_status` straight onto the column,
     * but that column is a ROLLUP: the moment anything recomputes it — and
     * creating a fulfilment does — Lunar rebuilds it from the transaction
     * ledger, and an order with no transactions falls back to `pending`. A
     * fixture that says "paid" without a capture is the two-places-one-truth
     * trap in miniature, and it shows up as a spurious status change halfway
     * through a test.
     *
     * So record the capture the fixture implies, once, before anything else
     * touches the order.
     */
    protected function reconcilePaymentLedger(Order $order): void
    {
        $claimed = (string) $order->payment_status;

        if (! in_array($claimed, ['paid', 'partially-refunded', 'refunded'], true)) {
            return;
        }

        if ($order->transactions()->exists()) {
            return;
        }

        $this->captureOrder($order);

        if ($claimed === 'refunded') {
            $this->refundOrder($order);
        }

        $order->refresh();
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

    /**
     * Hand the order's goods over — what "dispatched" means in this shop.
     *
     * Lunar 2.0 gives an order its fulfilments when it is placed
     * (`EnsureInitialFulfilment`, one per claiming method, each in that
     * method's default state), so dispatching does NOT create anything: it
     * advances the fulfilments that are already there. Creating another would
     * be rejected outright — the lines are already covered.
     *
     * `fulfil()` moves each to its method's canonical done state (shipped for a
     * delivery, collected for a pickup, provisioned for a digital good), which
     * is what the order-level rollup counts. Idempotent: anything already in a
     * terminal state is skipped, so a test can dispatch twice to prove the
     * second time changes nothing.
     */
    protected function fulfilOrder(Order $order): void
    {
        $this->ensureFulfilments($order);

        $outstanding = $order->fulfilments()
            ->get()
            ->filter(fn (Fulfilment $fulfilment) => FulfilFulfilment::canRun($fulfilment));

        foreach ($outstanding as $fulfilment) {
            $fulfilment->fulfil(notify: false);
        }
    }

    /**
     * Give the order something to hand over, if it has nothing.
     *
     * An order placed through checkout has real lines. A bare
     * `Order::factory()` one has none, and an order with nothing to fulfil
     * cannot be dispatched — `ResolveFulfilmentStatus` calls it settled by
     * definition, and the shop only counts goods that actually left.
     */
    protected function ensureFulfillableLine(Order $order): void
    {
        if ($order->fulfillableLines()->exists()) {
            return;
        }

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'type' => 'physical',
            'quantity' => 1,
        ]);

        $order->load('lines');

        // Resync the rollup quietly. An order with nothing to fulfil rolls up as
        // `fulfilled` (settled by definition), so a lineless fixture is sitting
        // on that value; giving it a line makes it `unfulfilled` without anything
        // having happened. Left alone, the next real recompute reports that as a
        // transition out of "dispatched" — a status change the order never made.
        // This is fixture repair, not a transition, so no event is raised: a real
        // order has its lines before any of this.
        $order->fulfilment_status = app(ResolvesFulfilmentStatus::class)->execute($order);
        $order->saveQuietly();
    }

    /**
     * Make sure the order has its fulfilments.
     *
     * An order placed through checkout already does — Lunar creates them at
     * placement — so this only fills in for a fixture built straight from the
     * factory, and it builds them the same way checkout would.
     */
    protected function ensureFulfilments(Order $order): void
    {
        if ($order->fulfilments()->exists()) {
            return;
        }

        $this->ensureFulfillableLine($order);

        app(EnsuresInitialFulfilment::class)->execute($order);
    }
}
