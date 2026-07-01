<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Modules\Checkout\Data\MoMoResult;
use Modules\Order\Events\OrderPaid;

/**
 * Reconciles a MoMo callback (return or IPN) against an order: verify the
 * signature, record the Transaction, and move the order to payment-received.
 * Idempotent — a duplicate callback (return + IPN, or retries) won't double
 * record or re-fire side effects. Mirrors {@see VNPayPaymentProcessor}.
 */
class MoMoPaymentProcessor
{
    public function __construct(
        protected MoMoGateway $gateway,
    ) {}

    public static function make(): self
    {
        return new self(MoMoGateway::fromConfig());
    }

    /**
     * @param  array<string, mixed>  $data  MoMo callback params
     */
    public function reconcile(array $data): MoMoResult
    {
        if (! $this->gateway->verify($data)) {
            return new MoMoResult(verified: false);
        }

        $orderId = $this->gateway->orderIdFrom($data);
        $order = $orderId ? Order::find($orderId) : null;

        if (! $order) {
            return new MoMoResult(verified: true, order: null);
        }

        $reference = (string) ($data['transId'] ?? $data['orderId'] ?? '');

        // Idempotency: if we've already recorded this gateway transaction, stop.
        $existing = Transaction::where('order_id', $order->id)
            ->where('driver', 'momo')
            ->where('reference', $reference)
            ->first();

        if ($existing) {
            return new MoMoResult(
                verified: true,
                paid: (bool) $existing->success,
                order: $order,
                alreadyProcessed: true,
            );
        }

        $success = $this->gateway->isSuccessful($data);
        // MoMo amount is whole VND; store in the order's minor unit.
        $decimals = $order->currency->decimal_places ?? 0;
        $amount = (int) round(((int) ($data['amount'] ?? 0)) * (10 ** $decimals));

        Transaction::create([
            'order_id' => $order->id,
            'success' => $success,
            'type' => 'capture',
            'driver' => 'momo',
            'amount' => $amount,
            'reference' => $reference,
            'status' => (string) ($data['resultCode'] ?? ''),
            // card_type is NOT NULL in lunar_transactions; default to empty.
            'card_type' => (string) ($data['payType'] ?? ''),
            'last_four' => '',
            'captured_at' => $success ? Carbon::now() : null,
            'meta' => $data,
        ]);

        if ($success) {
            $order->update([
                'status' => 'payment-received',
                'placed_at' => $order->placed_at ?? Carbon::now(),
            ]);

            // Domain signal for the payment-received email (+ future fulfilment).
            OrderPaid::dispatch($order);
        }

        return new MoMoResult(
            verified: true,
            paid: $success,
            order: $order,
            alreadyProcessed: false,
        );
    }
}
