<?php

namespace Tests\Feature;

use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\Transaction;
use Modules\Checkout\Services\VNPayPaymentProcessor;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * A callback in the wrong currency must never mark an order paid.
 *
 * Lunar's payment-integration guide says a driver should verify the payment
 * "amount **and currency**" against the order. The amount half was here; the
 * currency half was not — and without it the amount check is comparing numbers
 * in different units.
 *
 * Concretely: VNPay and MoMo settle only in VND. An order priced in a currency
 * with two decimal places has a total in *cents*, while the callback carries
 * *đồng*. 250.000 đồng arrives as a far larger integer than a $25.00 total, so
 * the "did they pay enough?" test passes on an order that was never funded.
 * The signature is valid, so nothing else objects.
 */
class GatewayCurrencyGuardTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.vnpay.tmn_code' => 'TESTCODE',
            'payment.vnpay.hash_secret' => self::SECRET,
            'payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'payment.vnpay.return_url' => 'http://localhost/payment/vnpay/return',
        ]);
    }

    private function placeOrder(): Order
    {
        $product = $this->createProduct(['stock' => 5]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'vnpay'])->assertSuccessful();

        return Order::latest('id')->firstOrFail();
    }

    /** @return array<string, string> a correctly signed VNPay success callback */
    private function signedCallback(Order $order, int $amountMinor): array
    {
        $decimals = $order->currency->decimal_places ?? 0;
        $major = $amountMinor / (10 ** $decimals);

        $params = [
            'vnp_Amount' => (string) (int) round($major * 100),
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => (string) $order->id,
            'vnp_TransactionNo' => '999888',
            'vnp_CurrCode' => 'VND',
        ];

        ksort($params);

        $hashData = implode('&', array_map(
            fn ($k, $v) => $k.'='.urlencode($v),
            array_keys($params),
            $params,
        ));

        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, self::SECRET);

        return $params;
    }

    public function test_a_matching_currency_is_accepted(): void
    {
        $this->seedBaseData();

        // Đặt tiền của shop đúng bằng loại cổng settle được.
        Currency::query()->update(['code' => 'VND', 'decimal_places' => 0]);

        $order = $this->placeOrder();
        $result = VNPayPaymentProcessor::make()->reconcile(
            $this->signedCallback($order, (int) $order->total),
        );

        $this->assertTrue($result->verified);
        $this->assertTrue($result->paid, 'Callback đúng tiền tệ và đủ tiền mà không được ghi nhận.');
    }

    public function test_a_mismatched_currency_is_refused(): void
    {
        $this->seedBaseData();

        // Shop tính tiền bằng loại cổng KHÔNG settle được.
        Currency::query()->update(['code' => 'USD', 'decimal_places' => 2]);

        $order = $this->placeOrder();
        $result = VNPayPaymentProcessor::make()->reconcile(
            $this->signedCallback($order, (int) $order->total),
        );

        $this->assertTrue($result->verified, 'Chữ ký vẫn hợp lệ — đó là điểm mấu chốt.');
        $this->assertFalse($result->paid, 'Đơn tính bằng USD mà cổng chỉ settle VND vẫn được ghi là đã thanh toán.');

        // Tiền vẫn phải để lại dấu vết: một giao dịch thất bại, không phải im lặng.
        $this->assertTrue(
            Transaction::where('order_id', $order->id)->where('success', false)->exists(),
            'Callback bị từ chối mà không ghi lại giao dịch nào — tiền trở nên vô hình.',
        );
    }
}
