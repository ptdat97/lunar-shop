<?php

namespace Modules\Checkout\PaymentTypes;

use Lunar\Base\DataTransferObjects\PaymentAuthorize;
use Lunar\Base\DataTransferObjects\PaymentCapture;
use Lunar\Base\DataTransferObjects\PaymentRefund;
use Lunar\Events\PaymentAttemptEvent;
use Lunar\Models\Contracts\Transaction as TransactionContract;
use Lunar\PaymentTypes\AbstractPayment;
use Modules\Checkout\Services\RefundService;

/**
 * VNPay payment driver. Online gateways are redirect-based, so "authorize"
 * only turns the cart into an order in the awaiting-payment state — the actual
 * payment is confirmed later by the VNPay return/IPN callback (which records
 * the Transaction and moves the order to payment-received). The redirect URL is
 * built by the storefront from the resulting order via VNPayGateway.
 */
class VNPayPayment extends AbstractPayment
{
    public function authorize(): ?PaymentAuthorize
    {
        if (! $this->order) {
            $this->order = $this->cart->draftOrder()->first() ?: $this->cart->createOrder();
        }

        $this->order->update([
            'status' => $this->config['authorized'] ?? 'awaiting-payment',
            'placed_at' => now(),
            'meta' => array_merge((array) $this->order->meta, [
                'payment_type' => 'vnpay',
            ]),
        ]);

        $response = new PaymentAuthorize(
            success: true,
            message: 'Awaiting VNPay payment.',
            orderId: $this->order->id,
            paymentType: 'vnpay',
        );

        // Same event the offline driver fires — order-confirmation email can
        // hook here. Payment-received email fires from the callback instead.
        PaymentAttemptEvent::dispatch($response);

        return $response;
    }

    public function refund(TransactionContract $transaction, int $amount = 0, $notes = null): PaymentRefund
    {
        // Delegate to the shared RefundService: it calls the VNPay merchant API,
        // records a `refund` Transaction, and updates the order.
        $result = app(RefundService::class)
            ->refund($transaction->order, $amount > 0 ? $amount : null, (string) ($notes ?? 'admin'));

        return new PaymentRefund($result->success, $result->message);
    }

    public function capture(TransactionContract $transaction, $amount = 0): PaymentCapture
    {
        return new PaymentCapture(true);
    }
}
