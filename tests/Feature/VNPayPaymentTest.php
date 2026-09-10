<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Order;
use Modules\Checkout\Services\CheckoutService;
use Modules\Checkout\Services\VNPayGateway;
use Modules\Checkout\Services\VNPayPaymentProcessor;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Order\Mail\OrderPaidMail;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class VNPayPaymentTest extends TestCase
{
    use CreatesStorefrontData;

    private const SECRET = 'TESTSECRET123';

    protected function setUp(): void
    {
        parent::setUp();
        // Cổng VNPay/MoMo chỉ settle VND, nên một đơn tính bằng loại tiền khác
        // là cấu hình bất khả thi — GatewayReconciler từ chối đúng như tài liệu
        // tích hợp thanh toán của Lunar yêu cầu. Seeder gốc tạo USD, nên test
        // cổng phải nói rõ tiền tệ thay vì mượn mặc định.
        Currency::query()->update(['code' => 'VND', 'decimal_places' => 0]);

        config([
            'payment.vnpay.tmn_code' => 'TESTCODE',
            'payment.vnpay.hash_secret' => self::SECRET,
            'payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'payment.vnpay.return_url' => 'http://localhost/payment/vnpay/return',
        ]);
    }

    /** Place a VNPay order through the real checkout flow; returns the Order. */
    private function placeVNPayOrder(): Order
    {
        $product = $this->createProduct(['price' => 5000]);
        CartSession::add($product->variants->first(), 1);
        $cart = CartSession::current();
        // Direct cart manipulation (bypassing CheckoutService) → Lunar requires a
        // postcode for order creation; the real flow defaults it in setAddresses.
        $address = $this->shippingPayload(['postcode' => '00000']);
        $cart->setShippingAddress($address);
        $cart->setBillingAddress($address);
        $cart->calculate();
        $cart->setShippingOption(ShippingManifest::getOptions($cart)->first())->calculate();

        return app(CheckoutService::class)->placeOrder('vnpay');
    }

    /** Build a signed callback array as VNPay would return it. */
    private function signedCallback(Order $order, array $overrides = []): array
    {
        $gateway = VNPayGateway::fromConfig();
        parse_str(parse_url($gateway->buildPaymentUrl($order, '127.0.0.1'), PHP_URL_QUERY), $q);
        $q = array_merge($q, [
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => '12345678',
            'vnp_BankCode' => 'NCB',
        ], $overrides);
        unset($q['vnp_SecureHash'], $q['vnp_SecureHashType']);
        ksort($q);
        $hashData = collect($q)->map(fn ($v, $k) => urlencode($k).'='.urlencode($v))->implode('&');
        $q['vnp_SecureHash'] = hash_hmac('sha512', $hashData, self::SECRET);

        return $q;
    }

    public function test_signature_round_trips_and_detects_tampering(): void
    {
        $order = $this->placeVNPayOrder();
        $gateway = VNPayGateway::fromConfig();
        $callback = $this->signedCallback($order);

        $this->assertTrue($gateway->verify($callback));

        $tampered = $callback;
        $tampered['vnp_Amount'] = '1';
        $this->assertFalse($gateway->verify($tampered));
    }

    public function test_place_vnpay_order_is_awaiting_payment_and_emails_confirmation(): void
    {
        Mail::fake();
        $order = $this->placeVNPayOrder();

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order));
        Mail::assertQueued(OrderConfirmationMail::class);
    }

    public function test_valid_callback_marks_paid_records_transaction_and_emails(): void
    {
        Mail::fake();
        $order = $this->placeVNPayOrder();

        $result = VNPayPaymentProcessor::make()->reconcile($this->signedCallback($order));

        $this->assertTrue($result->verified);
        $this->assertTrue($result->paid);
        $this->assertSame(OrderStatus::PAYMENT_RECEIVED, OrderStatus::of($order->fresh()));
        $this->assertDatabaseHas('lunar_transactions', [
            'order_id' => $order->id, 'driver' => 'vnpay', 'success' => true,
        ]);
        Mail::assertQueued(OrderPaidMail::class);
    }

    public function test_callback_is_idempotent(): void
    {
        $order = $this->placeVNPayOrder();
        $callback = $this->signedCallback($order);

        VNPayPaymentProcessor::make()->reconcile($callback);
        $second = VNPayPaymentProcessor::make()->reconcile($callback);

        $this->assertTrue($second->alreadyProcessed);
        $this->assertSame(1, $order->transactions()->where('driver', 'vnpay')->count());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->placeVNPayOrder();
        $callback = $this->signedCallback($order);
        $callback['vnp_SecureHash'] = 'deadbeef';

        $result = VNPayPaymentProcessor::make()->reconcile($callback);

        $this->assertFalse($result->verified);
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order->fresh()));
    }

    public function test_failed_payment_code_does_not_mark_paid(): void
    {
        $order = $this->placeVNPayOrder();
        $callback = $this->signedCallback($order, ['vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02']);

        $result = VNPayPaymentProcessor::make()->reconcile($callback);

        $this->assertTrue($result->verified);
        $this->assertFalse($result->paid);
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order->fresh()));
    }

    public function test_return_route_redirects_to_confirmation_when_paid(): void
    {
        $order = $this->placeVNPayOrder();
        $callback = $this->signedCallback($order);

        $this->get('/payment/vnpay/return?'.http_build_query($callback))
            ->assertRedirect(route('storefront.checkout.confirmation', $order->fresh()->public_id));
    }

    public function test_ipn_route_responses(): void
    {
        $order = $this->placeVNPayOrder();

        // Invalid signature → 97.
        $this->getJson('/payment/vnpay/ipn?vnp_TxnRef='.$order->id.'&vnp_SecureHash=bad')
            ->assertOk()->assertJsonPath('RspCode', '97');

        // Valid → 00 on first confirm.
        $this->getJson('/payment/vnpay/ipn?'.http_build_query($this->signedCallback($order)))
            ->assertOk()->assertJsonPath('RspCode', '00');
    }
}
