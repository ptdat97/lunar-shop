<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Modules\Checkout\Data\RefundResult;

/**
 * Refunds a paid VNPay/MoMo order via the gateway API, records a `refund`
 * Transaction linked to the original capture, and moves the order to `refunded`.
 * Idempotent-ish: a refund whose amount would exceed what's already refunded is
 * rejected; a fully-refunded order can't be refunded again.
 */
class RefundService
{
    /**
     * Refund an order (whole or partial).
     *
     * @param  int|null  $amount  minor units to refund; null = full remaining
     */
    public function refund(Order $order, ?int $amount = null, string $createdBy = 'admin'): RefundResult
    {
        $capture = $this->captureTransaction($order);

        if (! $capture) {
            return new RefundResult(success: false, message: 'No gateway capture found for this order.');
        }

        $driver = $capture->driver; // 'vnpay' | 'momo'
        $alreadyRefunded = $this->refundedTotal($order);
        $capturedAmount = (int) $capture->amount->value; // Price cast → minor units
        $remaining = max(0, $capturedAmount - $alreadyRefunded);

        $amount ??= $remaining;

        if ($amount <= 0 || $amount > $remaining) {
            return new RefundResult(success: false, message: 'Refund amount exceeds the refundable balance.');
        }

        $meta = (array) $capture->meta;

        $result = match ($driver) {
            'vnpay' => app(VNPayGateway::class)->refund($order, $amount, $meta, $createdBy),
            'momo' => app(MoMoGateway::class)->refund($order, $amount, $meta),
            default => ['success' => false, 'message' => "No refund handler for driver [{$driver}].", 'reference' => null],
        };

        if (! $result['success']) {
            return new RefundResult(success: false, message: $result['message'], driver: $driver);
        }

        Transaction::create([
            'order_id' => $order->id,
            'parent_transaction_id' => $capture->id,
            'success' => true,
            'type' => 'refund',
            'driver' => $driver,
            'amount' => $amount,
            'reference' => $result['reference'] ?? $this->fallbackReference($order),
            'status' => 'refunded',
            'card_type' => (string) ($capture->card_type ?? ''),
            'last_four' => '',
            'captured_at' => Carbon::now(),
            'meta' => ['refund_of' => $capture->reference, 'created_by' => $createdBy],
        ]);

        // Full refund → mark order refunded. Partial → keep paid status.
        if (($alreadyRefunded + $amount) >= $capturedAmount) {
            $order->update(['status' => 'refunded']);
        }

        return new RefundResult(success: true, message: $result['message'], driver: $driver, amount: $amount);
    }

    /**
     * The successful capture transaction for a gateway-paid order (vnpay/momo).
     */
    public function captureTransaction(Order $order): ?Transaction
    {
        return Transaction::where('order_id', $order->id)
            ->whereIn('driver', ['vnpay', 'momo'])
            ->where('type', 'capture')
            ->where('success', true)
            ->latest('id')
            ->first();
    }

    /**
     * A reference for a refund the gateway did not give one for.
     *
     * It used to be `refund-{order_id}`, which is the same string for every
     * partial refund on an order — two rows that reconciliation against the bank
     * statement cannot tell apart. A counter would race; the random suffix is
     * unique without a read, and still names the order it belongs to.
     */
    private function fallbackReference(Order $order): string
    {
        return "refund-{$order->id}-".Str::lower(Str::random(8));
    }

    /** Total already refunded (minor units) for an order. */
    public function refundedTotal(Order $order): int
    {
        return (int) Transaction::where('order_id', $order->id)
            ->where('type', 'refund')
            ->where('success', true)
            ->sum('amount');
    }

    /** Whether the order has a gateway capture that can still be refunded. */
    public function isRefundable(Order $order): bool
    {
        $capture = $this->captureTransaction($order);

        return $capture !== null && $this->refundedTotal($order) < (int) $capture->amount->value;
    }
}
