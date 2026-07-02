<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Lunar\Models\Order;

/**
 * VNPay gateway helper: builds the signed redirect URL and verifies the
 * signature on the return / IPN callbacks. Pure VNPay protocol — no order
 * mutation here (that lives in the driver / callback controller).
 *
 * Docs: https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html
 * - Params are sorted by key, urlencoded, joined as a query string, then
 *   signed with HMAC-SHA512 using the merchant hash secret.
 * - Amount is sent in the smallest VND unit × 100 (VNPay convention).
 */
class VNPayGateway
{
    public function __construct(
        protected string $tmnCode,
        protected string $hashSecret,
        protected string $paymentUrl,
        protected string $returnUrl,
        protected string $apiUrl = '',
    ) {}

    public static function fromConfig(): self
    {
        $settings = app(\Modules\Core\Support\Settings::class);

        return new self(
            (string) $settings->get('payment.vnpay.tmn_code'),
            (string) $settings->get('payment.vnpay.hash_secret'),
            (string) $settings->get('payment.vnpay.payment_url'),
            (string) $settings->get('payment.vnpay.return_url'),
            (string) $settings->get('payment.vnpay.api_url'),
        );
    }

    public function isConfigured(): bool
    {
        return $this->tmnCode !== '' && $this->hashSecret !== '';
    }

    /**
     * Build the signed payment redirect URL for an order.
     */
    public function buildPaymentUrl(Order $order, string $ipAddress, ?string $locale = 'vn'): string
    {
        // VNPay expects amount × 100. Order totals are stored in the currency's
        // minor unit; convert to the major unit first, then apply VNPay's ×100.
        $decimals = $order->currency->decimal_places ?? 0;
        $major = $order->total->value / (10 ** $decimals);
        $amount = (int) round($major * 100);

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->tmnCode,
            'vnp_Amount' => $amount,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => (string) $order->id,
            'vnp_OrderInfo' => 'Order '.$order->reference,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => $locale ?: 'vn',
            'vnp_ReturnUrl' => $this->returnUrl,
            'vnp_IpAddr' => $ipAddress,
            'vnp_CreateDate' => Carbon::now()->format('YmdHis'),
        ];

        ksort($params);
        $hashData = $this->hashData($params);
        $secureHash = hash_hmac('sha512', $hashData, $this->hashSecret);

        return $this->paymentUrl.'?'.$this->query($params)
            .'&vnp_SecureHashType=HmacSHA512&vnp_SecureHash='.$secureHash;
    }

    /**
     * Verify a callback's signature. Returns true when the recomputed hash
     * matches vnp_SecureHash over the remaining vnp_* params.
     *
     * @param  array<string, string>  $query
     */
    public function verify(array $query): bool
    {
        $received = $query['vnp_SecureHash'] ?? '';
        if ($received === '' || ! $this->isConfigured()) {
            return false;
        }

        $params = collect($query)
            ->reject(fn ($v, $k) => in_array($k, ['vnp_SecureHash', 'vnp_SecureHashType'], true))
            ->filter(fn ($v, $k) => str_starts_with($k, 'vnp_'))
            ->all();

        ksort($params);
        $expected = hash_hmac('sha512', $this->hashData($params), $this->hashSecret);

        return hash_equals($expected, $received);
    }

    /**
     * A callback is "paid" when both response and transaction codes are '00'.
     *
     * @param  array<string, string>  $query
     */
    public function isSuccessful(array $query): bool
    {
        return ($query['vnp_ResponseCode'] ?? null) === '00'
            && ($query['vnp_TransactionStatus'] ?? null) === '00';
    }

    /**
     * Refund (all or part of) a captured VNPay transaction via the merchant API.
     * `$amount` is in the currency's MINOR unit (order/transaction scale) and is
     * converted to VNPay's ×100 major unit here. `$captureMeta` is the stored
     * capture callback (Transaction->meta) — supplies vnp_TransactionNo + date.
     *
     * Returns ['success' => bool, 'message' => string, 'reference' => ?string].
     *
     * @param  array<string, mixed>  $captureMeta
     * @return array{success:bool, message:string, reference:?string}
     */
    public function refund(Order $order, int $amount, array $captureMeta, string $createdBy = 'system', string $ipAddress = '127.0.0.1'): array
    {
        if (! $this->isConfigured() || $this->apiUrl === '') {
            return ['success' => false, 'message' => 'VNPay refund is not configured.', 'reference' => null];
        }

        $decimals = $order->currency->decimal_places ?? 0;
        $vnpAmount = (int) round($amount / (10 ** $decimals) * 100);

        $requestId = (string) \Illuminate\Support\Str::uuid();
        $createDate = Carbon::now()->format('YmdHis');
        // Full vs partial refund: 02 = full, 03 = partial.
        $orderTotalMinor = (int) $order->total->value;
        $transactionType = $amount >= $orderTotalMinor ? '02' : '03';
        $txnDate = (string) ($captureMeta['vnp_PayDate'] ?? $captureMeta['vnp_CreateDate'] ?? $createDate);
        $transactionNo = (string) ($captureMeta['vnp_TransactionNo'] ?? '');
        $orderInfo = 'Refund order ' . $order->reference;

        // VNPay refund signature: fixed pipe-joined field order (per API docs).
        $data = implode('|', [
            $requestId, '2.1.0', 'refund', $this->tmnCode, $transactionType,
            (string) $order->id, (string) $vnpAmount, $transactionNo, $txnDate,
            $createdBy, $createDate, $ipAddress, $orderInfo,
        ]);
        $secureHash = hash_hmac('sha512', $data, $this->hashSecret);

        $response = Http::asJson()->post($this->apiUrl, [
            'vnp_RequestId' => $requestId,
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'refund',
            'vnp_TmnCode' => $this->tmnCode,
            'vnp_TransactionType' => $transactionType,
            'vnp_TxnRef' => (string) $order->id,
            'vnp_Amount' => $vnpAmount,
            'vnp_TransactionNo' => $transactionNo,
            'vnp_TransactionDate' => $txnDate,
            'vnp_CreateBy' => $createdBy,
            'vnp_CreateDate' => $createDate,
            'vnp_IpAddr' => $ipAddress,
            'vnp_OrderInfo' => $orderInfo,
            'vnp_SecureHash' => $secureHash,
        ]);

        $body = $response->json() ?? [];
        $success = ($body['vnp_ResponseCode'] ?? null) === '00';

        return [
            'success' => $success,
            'message' => (string) ($body['vnp_Message'] ?? ($success ? 'Refunded.' : 'Refund failed.')),
            'reference' => $body['vnp_TransactionNo'] ?? null,
        ];
    }

    /**
     * Build the raw hash string: urlencoded key=value pairs joined with '&'.
     *
     * @param  array<string, mixed>  $params
     */
    protected function hashData(array $params): string
    {
        return collect($params)
            ->map(fn ($value, $key) => urlencode((string) $key).'='.urlencode((string) $value))
            ->implode('&');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function query(array $params): string
    {
        return collect($params)
            ->map(fn ($value, $key) => urlencode((string) $key).'='.urlencode((string) $value))
            ->implode('&');
    }
}
