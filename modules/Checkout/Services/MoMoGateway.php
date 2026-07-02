<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Facades\Http;
use Lunar\Models\Order;

/**
 * MoMo gateway helper: creates a payment (signed JSON POST → payUrl) and
 * verifies the signature on the return / IPN callbacks. Pure MoMo protocol —
 * no order mutation here (that lives in the processor / callback controller).
 *
 * Docs: https://developers.momo.vn/v3/vi/docs/payment/api/wallet/onetime
 * - Signature is HMAC-SHA256 over an ordered `key=value&…` raw string (the key
 *   order is fixed by MoMo, alphabetical for both create + callback).
 * - Amount is sent in whole VND (MoMo is VND-only, no minor unit).
 */
class MoMoGateway
{
    public function __construct(
        protected string $partnerCode,
        protected string $accessKey,
        protected string $secretKey,
        protected string $endpoint,
        protected string $returnUrl,
        protected string $ipnUrl,
        protected string $refundUrl = '',
    ) {}

    public static function fromConfig(): self
    {
        $settings = app(\Modules\Core\Support\Settings::class);

        return new self(
            (string) $settings->get('payment.momo.partner_code'),
            (string) $settings->get('payment.momo.access_key'),
            (string) $settings->get('payment.momo.secret_key'),
            (string) $settings->get('payment.momo.endpoint'),
            (string) $settings->get('payment.momo.return_url'),
            (string) $settings->get('payment.momo.ipn_url'),
            (string) $settings->get('payment.momo.refund_url'),
        );
    }

    public function isConfigured(): bool
    {
        return $this->partnerCode !== '' && $this->accessKey !== '' && $this->secretKey !== '';
    }

    /**
     * Whole-VND amount for an order (MoMo has no minor unit).
     */
    public function amountFor(Order $order): int
    {
        $decimals = $order->currency->decimal_places ?? 0;

        return (int) round($order->total->value / (10 ** $decimals));
    }

    /**
     * Create a MoMo payment and return the payUrl to redirect the shopper to.
     * Throws a RuntimeException with MoMo's message when creation fails.
     */
    public function createPayment(Order $order, ?string $locale = 'vi'): string
    {
        $amount = (string) $this->amountFor($order);
        $orderId = $order->id . '-' . now()->timestamp; // unique per attempt
        $requestId = (string) \Illuminate\Support\Str::uuid();
        $orderInfo = 'Order ' . $order->reference;
        $extraData = '';
        $requestType = 'captureWallet';

        // MoMo's create signature — fields in the exact documented order.
        $raw = "accessKey={$this->accessKey}&amount={$amount}&extraData={$extraData}"
            . "&ipnUrl={$this->ipnUrl}&orderId={$orderId}&orderInfo={$orderInfo}"
            . "&partnerCode={$this->partnerCode}&redirectUrl={$this->returnUrl}"
            . "&requestId={$requestId}&requestType={$requestType}";

        $signature = hash_hmac('sha256', $raw, $this->secretKey);

        $response = Http::asJson()->post($this->endpoint, [
            'partnerCode' => $this->partnerCode,
            'accessKey' => $this->accessKey,
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $this->returnUrl,
            'ipnUrl' => $this->ipnUrl,
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature,
            'lang' => $locale ?: 'vi',
        ]);

        $body = $response->json() ?? [];

        if (($body['resultCode'] ?? null) !== 0 || empty($body['payUrl'])) {
            throw new \RuntimeException($body['message'] ?? 'MoMo payment creation failed.');
        }

        return $body['payUrl'];
    }

    /**
     * Verify a callback's signature. MoMo signs the result with a fixed field
     * order; recompute and constant-time compare.
     *
     * @param  array<string, mixed>  $data
     */
    public function verify(array $data): bool
    {
        $received = (string) ($data['signature'] ?? '');
        if ($received === '' || ! $this->isConfigured()) {
            return false;
        }

        $raw = "accessKey={$this->accessKey}"
            . '&amount=' . ($data['amount'] ?? '')
            . '&extraData=' . ($data['extraData'] ?? '')
            . '&message=' . ($data['message'] ?? '')
            . '&orderId=' . ($data['orderId'] ?? '')
            . '&orderInfo=' . ($data['orderInfo'] ?? '')
            . '&orderType=' . ($data['orderType'] ?? '')
            . '&partnerCode=' . ($data['partnerCode'] ?? '')
            . '&payType=' . ($data['payType'] ?? '')
            . '&requestId=' . ($data['requestId'] ?? '')
            . '&responseTime=' . ($data['responseTime'] ?? '')
            . '&resultCode=' . ($data['resultCode'] ?? '')
            . '&transId=' . ($data['transId'] ?? '');

        $expected = hash_hmac('sha256', $raw, $this->secretKey);

        return hash_equals($expected, $received);
    }

    /**
     * Sign a callback payload the way MoMo does (used to build test fixtures and
     * mirror the verify field order in one place).
     *
     * @param  array<string, mixed>  $data
     */
    public function sign(array $data): string
    {
        $raw = "accessKey={$this->accessKey}"
            . '&amount=' . ($data['amount'] ?? '')
            . '&extraData=' . ($data['extraData'] ?? '')
            . '&message=' . ($data['message'] ?? '')
            . '&orderId=' . ($data['orderId'] ?? '')
            . '&orderInfo=' . ($data['orderInfo'] ?? '')
            . '&orderType=' . ($data['orderType'] ?? '')
            . '&partnerCode=' . ($data['partnerCode'] ?? '')
            . '&payType=' . ($data['payType'] ?? '')
            . '&requestId=' . ($data['requestId'] ?? '')
            . '&responseTime=' . ($data['responseTime'] ?? '')
            . '&resultCode=' . ($data['resultCode'] ?? '')
            . '&transId=' . ($data['transId'] ?? '');

        return hash_hmac('sha256', $raw, $this->secretKey);
    }

    /**
     * A callback is "paid" when resultCode is 0.
     *
     * @param  array<string, mixed>  $data
     */
    public function isSuccessful(array $data): bool
    {
        return (int) ($data['resultCode'] ?? -1) === 0;
    }

    /**
     * Extract the internal order id from MoMo's orderId ("{id}-{timestamp}").
     *
     * @param  array<string, mixed>  $data
     */
    public function orderIdFrom(array $data): ?int
    {
        $orderId = (string) ($data['orderId'] ?? '');

        if ($orderId === '') {
            return null;
        }

        return (int) explode('-', $orderId)[0];
    }

    /**
     * Refund (all or part of) a captured MoMo transaction via the refund API.
     * `$amount` is in the currency's MINOR unit and converted to whole VND here.
     * `$captureMeta` is the stored capture callback (Transaction->meta) —
     * supplies the original transId to refund against.
     *
     * Returns ['success' => bool, 'message' => string, 'reference' => ?string].
     *
     * @param  array<string, mixed>  $captureMeta
     * @return array{success:bool, message:string, reference:?string}
     */
    public function refund(Order $order, int $amount, array $captureMeta, ?string $locale = 'vi'): array
    {
        if (! $this->isConfigured() || $this->refundUrl === '') {
            return ['success' => false, 'message' => 'MoMo refund is not configured.', 'reference' => null];
        }

        $decimals = $order->currency->decimal_places ?? 0;
        $momoAmount = (string) (int) round($amount / (10 ** $decimals));
        $transId = (string) ($captureMeta['transId'] ?? '');
        $orderId = $order->id . '-refund-' . now()->timestamp;
        $requestId = (string) \Illuminate\Support\Str::uuid();
        $description = 'Refund order ' . $order->reference;

        // MoMo refund signature — fixed field order.
        $raw = "accessKey={$this->accessKey}&amount={$momoAmount}&description={$description}"
            . "&orderId={$orderId}&partnerCode={$this->partnerCode}&requestId={$requestId}"
            . "&transId={$transId}";
        $signature = hash_hmac('sha256', $raw, $this->secretKey);

        $response = Http::asJson()->post($this->refundUrl, [
            'partnerCode' => $this->partnerCode,
            'orderId' => $orderId,
            'requestId' => $requestId,
            'amount' => $momoAmount,
            'transId' => $transId,
            'lang' => $locale ?: 'vi',
            'description' => $description,
            'signature' => $signature,
        ]);

        $body = $response->json() ?? [];
        $success = (int) ($body['resultCode'] ?? -1) === 0;

        return [
            'success' => $success,
            'message' => (string) ($body['message'] ?? ($success ? 'Refunded.' : 'Refund failed.')),
            'reference' => isset($body['transId']) ? (string) $body['transId'] : null,
        ];
    }
}
