<?php

namespace Modules\Payment\Services;

use Illuminate\Support\Carbon;
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
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('payment.vnpay.tmn_code'),
            (string) config('payment.vnpay.hash_secret'),
            (string) config('payment.vnpay.payment_url'),
            (string) config('payment.vnpay.return_url'),
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
