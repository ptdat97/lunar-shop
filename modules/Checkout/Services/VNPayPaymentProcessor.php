<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Modules\Order\Events\OrderPaid;
use Modules\Checkout\Data\VNPayResult;

/**
 * Reconciles a VNPay callback (return or IPN) against an order: verify the
 * signature, record the Transaction, and move the order to payment-received.
 * Idempotent — a duplicate callback (return + IPN, or retries) won't double
 * record or re-fire side effects.
 */
class VNPayPaymentProcessor
{
    public function __construct(
        protected VNPayGateway $gateway,
    ) {}

    public static function make(): self
    {
        return new self(VNPayGateway::fromConfig());
    }

    /**
     * @param  array<string, string>  $query  VNPay callback params
     */
    public function reconcile(array $query): VNPayResult
    {
        if (! $this->gateway->verify($query)) {
            return new VNPayResult(verified: false);
        }

        $order = isset($query['vnp_TxnRef'])
            ? Order::find((int) $query['vnp_TxnRef'])
            : null;

        if (! $order) {
            return new VNPayResult(verified: true, order: null);
        }

        $reference = $query['vnp_TransactionNo'] ?? ($query['vnp_TxnRef'] ?? null);

        // Idempotency: if we've already recorded this gateway transaction, stop.
        $existing = Transaction::where('order_id', $order->id)
            ->where('driver', 'vnpay')
            ->where('reference', $reference)
            ->first();

        if ($existing) {
            return new VNPayResult(
                verified: true,
                paid: (bool) $existing->success,
                order: $order,
                alreadyProcessed: true,
            );
        }

        $success = $this->gateway->isSuccessful($query);
        // VNPay amount is ×100 of the major unit; store in the order's minor unit.
        $decimals = $order->currency->decimal_places ?? 0;
        $amount = (int) round(((int) ($query['vnp_Amount'] ?? 0)) / 100 * (10 ** $decimals));

        Transaction::create([
            'order_id' => $order->id,
            'success' => $success,
            'type' => 'capture',
            'driver' => 'vnpay',
            'amount' => $amount,
            'reference' => $reference,
            'status' => (string) ($query['vnp_ResponseCode'] ?? ''),
            // card_type is NOT NULL in lunar_transactions; default to empty.
            'card_type' => $query['vnp_BankCode'] ?? '',
            'last_four' => '',
            'captured_at' => $success ? Carbon::now() : null,
            'meta' => $query,
        ]);

        if ($success) {
            $order->update([
                'status' => 'payment-received',
                'placed_at' => $order->placed_at ?? Carbon::now(),
            ]);

            // Domain signal for the payment-received email (+ future fulfilment).
            OrderPaid::dispatch($order);
        }

        return new VNPayResult(
            verified: true,
            paid: $success,
            order: $order,
            alreadyProcessed: false,
        );
    }
}
