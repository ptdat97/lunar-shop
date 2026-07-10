<?php

namespace Modules\Checkout\Services;

use Lunar\Models\Order;
use Modules\Checkout\Data\VNPayResult;

/**
 * Reconciles a VNPay callback (return or IPN) against an order.
 *
 * The rules — signature, amount, closed-order and concurrency guards — live in
 * {@see GatewayReconciler}. Only the VNPay-specific field names are here.
 */
class VNPayPaymentProcessor extends GatewayReconciler
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
        $r = $this->reconcilePayload($query);

        return new VNPayResult(
            verified: $r['verified'],
            paid: $r['paid'],
            order: $r['order'],
            alreadyProcessed: $r['alreadyProcessed'],
        );
    }

    protected function driver(): string
    {
        return 'vnpay';
    }

    protected function verifySignature(array $payload): bool
    {
        return $this->gateway->verify($payload);
    }

    protected function isSuccessful(array $payload): bool
    {
        return $this->gateway->isSuccessful($payload);
    }

    protected function resolveOrder(array $payload): ?Order
    {
        return isset($payload['vnp_TxnRef'])
            ? Order::find((int) $payload['vnp_TxnRef'])
            : null;
    }

    protected function reference(array $payload): string
    {
        return (string) ($payload['vnp_TransactionNo'] ?? ($payload['vnp_TxnRef'] ?? ''));
    }

    /** VNPay quotes the amount ×100 of the major unit; rescale to the order's minor unit. */
    protected function paidAmount(array $payload, Order $order): int
    {
        $decimals = $order->currency->decimal_places ?? 0;

        return (int) round(((int) ($payload['vnp_Amount'] ?? 0)) / 100 * (10 ** $decimals));
    }

    protected function statusCode(array $payload): string
    {
        return (string) ($payload['vnp_ResponseCode'] ?? '');
    }

    protected function cardType(array $payload): string
    {
        return (string) ($payload['vnp_BankCode'] ?? '');
    }
}
