<?php

namespace Modules\Checkout\Services;

use Lunar\Models\Order;
use Modules\Checkout\Data\MoMoResult;

/**
 * Reconciles a MoMo callback (return or IPN) against an order.
 *
 * The rules — signature, amount, closed-order and concurrency guards — live in
 * {@see GatewayReconciler}. Only the MoMo-specific field names are here.
 */
class MoMoPaymentProcessor extends GatewayReconciler
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
        $r = $this->reconcilePayload($data);

        return new MoMoResult(
            verified: $r['verified'],
            paid: $r['paid'],
            order: $r['order'],
            alreadyProcessed: $r['alreadyProcessed'],
        );
    }

    protected function driver(): string
    {
        return 'momo';
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
        $orderId = $this->gateway->orderIdFrom($payload);

        return $orderId ? Order::find($orderId) : null;
    }

    protected function reference(array $payload): string
    {
        return (string) ($payload['transId'] ?? $payload['orderId'] ?? '');
    }

    /** MoMo quotes whole VND; rescale to the order's minor unit. */
    protected function paidAmount(array $payload, Order $order): int
    {
        $decimals = $order->currency->decimal_places ?? 0;

        return (int) round(((int) ($payload['amount'] ?? 0)) * (10 ** $decimals));
    }

    protected function statusCode(array $payload): string
    {
        return (string) ($payload['resultCode'] ?? '');
    }

    protected function cardType(array $payload): string
    {
        return (string) ($payload['payType'] ?? '');
    }
}
