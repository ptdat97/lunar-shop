<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Lunar\Facades\CartSession;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Order;
use Modules\Checkout\Services\MoMoGateway;
use Modules\Checkout\Services\MoMoPaymentProcessor;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Order\Mail\OrderPaidMail;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class MoMoPaymentTest extends TestCase
{
    use CreatesStorefrontData;

    private const SECRET = 'MOMOSECRET123';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payment.momo.partner_code' => 'MOMOTEST',
            'payment.momo.access_key' => 'ACCESSKEY',
            'payment.momo.secret_key' => self::SECRET,
            'payment.momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'payment.momo.return_url' => 'http://localhost/payment/momo/return',
            'payment.momo.ipn_url' => 'http://localhost/payment/momo/ipn',
        ]);
    }

    /** Place a MoMo order through the real checkout flow; returns the Order. */
    private function placeMoMoOrder(): Order
    {
        $product = $this->createProduct(['price' => 5000]);
        CartSession::add($product->variants->first(), 1);
        $cart = CartSession::current();
        $address = $this->shippingPayload(['postcode' => '00000']);
        $cart->setShippingAddress($address);
        $cart->setBillingAddress($address);
        $cart->calculate();
        $cart->setShippingOption(ShippingManifest::getOptions($cart)->first())->calculate();

        return app(\Modules\Checkout\Services\CheckoutService::class)->placeOrder('momo');
    }

    /** Build a signed callback array as MoMo would send it. */
    private function signedCallback(Order $order, array $overrides = []): array
    {
        $gateway = MoMoGateway::fromConfig();
        $data = array_merge([
            'partnerCode' => 'MOMOTEST',
            'orderId' => $order->id . '-1699999999',
            'requestId' => 'req-1',
            'amount' => (string) $gateway->amountFor($order),
            'orderInfo' => 'Order ' . $order->reference,
            'orderType' => 'momo_wallet',
            'transId' => '2887880',
            'resultCode' => '0',
            'message' => 'Successful.',
            'payType' => 'qr',
            'responseTime' => '1699999999',
            'extraData' => '',
        ], $overrides);

        // Sign unless the caller supplied an explicit (e.g. bogus) signature.
        $data['signature'] = $overrides['signature'] ?? $gateway->sign($data);

        return $data;
    }

    public function test_signature_round_trips_and_detects_tampering(): void
    {
        $gateway = MoMoGateway::fromConfig();
        $order = $this->placeMoMoOrder();

        $callback = $this->signedCallback($order);
        $this->assertTrue($gateway->verify($callback));

        $tampered = $callback;
        $tampered['amount'] = '1';
        $this->assertFalse($gateway->verify($tampered));
    }

    public function test_place_momo_order_is_awaiting_payment_and_emails_confirmation(): void
    {
        Mail::fake();
        $order = $this->placeMoMoOrder();

        $this->assertSame('awaiting-payment', $order->status);
        $this->assertSame('momo', $order->meta['payment_type'] ?? null);
        Mail::assertQueued(OrderConfirmationMail::class);
    }

    public function test_create_payment_returns_pay_url(): void
    {
        Http::fake([
            '*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://momo.test/pay/abc'], 200),
        ]);

        $order = $this->placeMoMoOrder();
        $url = MoMoGateway::fromConfig()->createPayment($order);

        $this->assertSame('https://momo.test/pay/abc', $url);
    }

    public function test_create_payment_throws_on_gateway_error(): void
    {
        Http::fake([
            '*' => Http::response(['resultCode' => 99, 'message' => 'Bad request'], 200),
        ]);

        $order = $this->placeMoMoOrder();

        $this->expectException(\RuntimeException::class);
        MoMoGateway::fromConfig()->createPayment($order);
    }

    public function test_valid_callback_marks_paid_records_transaction_and_emails(): void
    {
        Mail::fake();
        $order = $this->placeMoMoOrder();

        $result = MoMoPaymentProcessor::make()->reconcile($this->signedCallback($order));

        $this->assertTrue($result->verified);
        $this->assertTrue($result->paid);
        $this->assertSame('payment-received', $order->fresh()->status);
        $this->assertDatabaseHas('lunar_transactions', [
            'order_id' => $order->id,
            'driver' => 'momo',
            'success' => true,
        ]);
        Mail::assertQueued(OrderPaidMail::class);
    }

    public function test_callback_is_idempotent(): void
    {
        $order = $this->placeMoMoOrder();
        $callback = $this->signedCallback($order);

        MoMoPaymentProcessor::make()->reconcile($callback);
        $second = MoMoPaymentProcessor::make()->reconcile($callback);

        $this->assertTrue($second->alreadyProcessed);
        $this->assertSame(1, $order->transactions()->where('driver', 'momo')->count());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->placeMoMoOrder();
        $callback = $this->signedCallback($order, ['signature' => 'deadbeef']);

        $result = MoMoPaymentProcessor::make()->reconcile($callback);

        $this->assertFalse($result->verified);
    }

    public function test_failed_result_code_records_unpaid(): void
    {
        $order = $this->placeMoMoOrder();
        $callback = $this->signedCallback($order, ['resultCode' => '1006']);

        $result = MoMoPaymentProcessor::make()->reconcile($callback);

        $this->assertTrue($result->verified);
        $this->assertFalse($result->paid);
        $this->assertSame('awaiting-payment', $order->fresh()->status);
    }

    public function test_ipn_endpoint_returns_204(): void
    {
        $order = $this->placeMoMoOrder();

        $this->postJson('/payment/momo/ipn', $this->signedCallback($order))
            ->assertNoContent();

        $this->assertSame('payment-received', $order->fresh()->status);
    }
}
