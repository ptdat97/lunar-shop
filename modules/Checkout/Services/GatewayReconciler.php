<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Support\OrderStatus;

/**
 * The rules every payment-gateway callback must obey, in one place.
 *
 * VNPay and MoMo previously carried identical copies of this logic, and the
 * copies were identically wrong: a validly-signed callback was trusted for the
 * *amount* it claimed, could resurrect a cancelled order whose stock had
 * already been returned, and relied on an unlocked `SELECT` to stay idempotent
 * while the return-URL and the IPN raced each other.
 *
 * A valid signature proves the gateway sent the message. It does not prove the
 * message is about the right amount, or that we still want it.
 *
 * Subclasses supply only what genuinely differs per gateway; the guards below
 * are not overridable by design.
 */
abstract class GatewayReconciler
{
    /** The `driver` value stored on the Transaction row. */
    abstract protected function driver(): string;

    /** Verify the gateway's signature over the raw callback. */
    abstract protected function verifySignature(array $payload): bool;

    /** Did the gateway say the payment succeeded? */
    abstract protected function isSuccessful(array $payload): bool;

    /** The order this callback refers to, or null. */
    abstract protected function resolveOrder(array $payload): ?Order;

    /** The gateway's own transaction id — the idempotency key. */
    abstract protected function reference(array $payload): string;

    /** The amount the gateway says was paid, converted to the order's minor unit. */
    abstract protected function paidAmount(array $payload, Order $order): int;

    /** Gateway status code, stored verbatim for support/debugging. */
    abstract protected function statusCode(array $payload): string;

    /** Card/bank/wallet type, if the gateway reports one. */
    abstract protected function cardType(array $payload): string;

    /**
     * Reconcile one callback (return URL or IPN).
     *
     * @param  array<string, mixed>  $payload
     * @return array{verified:bool, paid:bool, order:?Order, alreadyProcessed:bool}
     */
    protected function reconcilePayload(array $payload): array
    {
        if (! $this->verifySignature($payload)) {
            return $this->outcome(verified: false);
        }

        $order = $this->resolveOrder($payload);

        if (! $order) {
            return $this->outcome(verified: true);
        }

        $reference = $this->reference($payload);

        // Serialise concurrent callbacks for this order. The return URL and the
        // IPN routinely arrive together; without the lock both would pass the
        // "already recorded?" check and each write a Transaction + fire OrderPaid.
        return DB::transaction(function () use ($payload, $order, $reference) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            $existing = Transaction::where('order_id', $order->id)
                ->where('driver', $this->driver())
                ->where('type', 'capture')
                ->where('reference', $reference)
                ->first();

            if ($existing) {
                return $this->outcome(
                    verified: true,
                    paid: (bool) $existing->success,
                    order: $order,
                    alreadyProcessed: true,
                );
            }

            $success = $this->isSuccessful($payload);
            $amount = $this->paidAmount($payload, $order);

            // A closed order has already handed its stock back. Record the money
            // so it is never invisible, but do not revive the order — a human
            // must decide whether to refund or re-ship.
            if ($success && OrderStatus::isClosed($order->status)) {
                $this->recordTransaction($order, $payload, $reference, $amount, success: true);

                Log::warning('Payment landed on a closed order.', [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'driver' => $this->driver(),
                    'reference' => $reference,
                    'amount' => $amount,
                ]);

                return $this->outcome(verified: true, paid: false, order: $order);
            }

            // The signature proves provenance, not price. Underpayment must never
            // mark an order paid; overpayment is recorded and flagged, not refused.
            if ($success && $amount < (int) $order->total->value) {
                $this->recordTransaction($order, $payload, $reference, $amount, success: false);

                Log::warning('Gateway callback underpaid the order.', [
                    'order_id' => $order->id,
                    'expected' => (int) $order->total->value,
                    'received' => $amount,
                    'driver' => $this->driver(),
                    'reference' => $reference,
                ]);

                return $this->outcome(verified: true, paid: false, order: $order);
            }

            $this->recordTransaction($order, $payload, $reference, $amount, $success);

            if (! $success) {
                return $this->outcome(verified: true, paid: false, order: $order);
            }

            if ($amount > (int) $order->total->value) {
                Log::warning('Gateway callback overpaid the order.', [
                    'order_id' => $order->id,
                    'expected' => (int) $order->total->value,
                    'received' => $amount,
                    'driver' => $this->driver(),
                ]);
            }

            $order->update([
                'status' => OrderStatus::PAYMENT_RECEIVED,
                'placed_at' => $order->placed_at ?? Carbon::now(),
            ]);

            // Domain signal for the payment-received email (+ future fulfilment).
            OrderPaid::dispatch($order);

            return $this->outcome(verified: true, paid: true, order: $order);
        });
    }

    /** @param array<string, mixed> $payload */
    private function recordTransaction(Order $order, array $payload, string $reference, int $amount, bool $success): void
    {
        Transaction::create([
            'order_id' => $order->id,
            'success' => $success,
            'type' => 'capture',
            'driver' => $this->driver(),
            'amount' => $amount,
            'reference' => $reference,
            'status' => $this->statusCode($payload),
            // card_type is NOT NULL in lunar_transactions; default to empty.
            'card_type' => $this->cardType($payload),
            'last_four' => '',
            'captured_at' => $success ? Carbon::now() : null,
            'meta' => $payload,
        ]);
    }

    /** @return array{verified:bool, paid:bool, order:?Order, alreadyProcessed:bool} */
    private function outcome(bool $verified = false, bool $paid = false, ?Order $order = null, bool $alreadyProcessed = false): array
    {
        return compact('verified', 'paid', 'order', 'alreadyProcessed');
    }
}
