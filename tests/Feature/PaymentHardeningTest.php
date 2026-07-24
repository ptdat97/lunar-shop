<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Lunar\Facades\CartSession;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Modules\Catalog\Models\ProductSku;
use Modules\Checkout\Services\CheckoutService;
use Modules\Checkout\Services\GatewayReconciler;
use Modules\Checkout\Services\VNPayGateway;
use Modules\Checkout\Services\VNPayPaymentProcessor;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * A valid gateway signature proves the message came from the gateway. It does
 * not prove the amount is right, or that we still want the money.
 *
 * These guard {@see GatewayReconciler}, shared by the
 * VNPay and MoMo processors.
 */
class PaymentHardeningTest extends TestCase
{
    use CreatesStorefrontData;

    private const SECRET = 'TESTSECRET123';

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

    private function placeVNPayOrder(int $price = 5000, int $stock = 10): Order
    {
        $product = $this->createProduct(['price' => $price]);
        $product->skus->first()->update(['quantity' => $stock]);
        CartSession::add($product->skus->first(), 1);
        $cart = CartSession::current();
        $address = $this->shippingPayload(['postcode' => '00000']);
        $cart->setShippingAddress($address);
        $cart->setBillingAddress($address);
        $cart->calculate();
        $cart->setShippingOption(ShippingManifest::getOptions($cart)->first())->calculate();

        return app(CheckoutService::class)->placeOrder('vnpay');
    }

    /** Sign a callback exactly as VNPay would, allowing field overrides. */
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

    public function test_underpaid_callback_does_not_mark_order_paid(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->placeVNPayOrder();

        // Genuinely signed, but for 1.00 instead of the order total.
        $result = VNPayPaymentProcessor::make()->reconcile(
            $this->signedCallback($order, ['vnp_Amount' => '100'])
        );

        $this->assertTrue($result->verified, 'signature is valid');
        $this->assertFalse($result->paid);
        $this->assertNotSame(OrderStatus::PAYMENT_RECEIVED, $order->fresh()->status);
        Event::assertNotDispatched(OrderPaid::class);

        // The money is still recorded — an unexplained payment must never vanish.
        $tx = Transaction::where('order_id', $order->id)->sole();
        $this->assertFalse((bool) $tx->success);
        $this->assertSame(100, (int) $tx->amount->value);
    }

    public function test_exact_amount_marks_order_paid(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->placeVNPayOrder();

        $result = VNPayPaymentProcessor::make()->reconcile($this->signedCallback($order));

        $this->assertTrue($result->paid);
        $this->assertSame(OrderStatus::PAYMENT_RECEIVED, $order->fresh()->status);
        Event::assertDispatched(OrderPaid::class);
    }

    public function test_overpaid_callback_still_marks_order_paid(): void
    {
        $order = $this->placeVNPayOrder();
        $over = (string) (((int) $order->total->value) + 1000);

        $result = VNPayPaymentProcessor::make()->reconcile(
            $this->signedCallback($order, ['vnp_Amount' => $over])
        );

        // Refusing the goods to a customer who overpaid helps nobody; it is logged.
        $this->assertTrue($result->paid);
        $this->assertSame(OrderStatus::PAYMENT_RECEIVED, $order->fresh()->status);
    }

    public function test_late_callback_does_not_revive_a_cancelled_order(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->placeVNPayOrder(stock: 10);
        $variant = ProductSku::find($order->lines->first()->purchasable_id);
        $this->assertSame(9, $variant->fresh()->getTotalInventory(), 'reserved at order creation');

        // Abandoned: cancelled, stock returned by ReleaseStockOnOrderClosed.
        $order->update(['status' => OrderStatus::CANCELLED]);
        $this->assertSame(10, $variant->fresh()->getTotalInventory());
        $this->assertNotNull($order->fresh()->stock_released_at);

        $result = VNPayPaymentProcessor::make()->reconcile($this->signedCallback($order));

        $this->assertFalse($result->paid);
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(10, $variant->fresh()->getTotalInventory(), 'must not silently oversell');
        Event::assertNotDispatched(OrderPaid::class);

        // Recorded so an operator can refund it.
        $this->assertSame(1, Transaction::where('order_id', $order->id)->count());
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        Event::fake([OrderPaid::class]);
        $order = $this->placeVNPayOrder();
        $callback = $this->signedCallback($order);

        $first = VNPayPaymentProcessor::make()->reconcile($callback);
        $second = VNPayPaymentProcessor::make()->reconcile($callback);

        $this->assertFalse($first->alreadyProcessed);
        $this->assertTrue($second->alreadyProcessed);
        $this->assertTrue($second->paid);

        $this->assertSame(1, Transaction::where('order_id', $order->id)->count());
        Event::assertDispatchedTimes(OrderPaid::class, 1);
    }

    /**
     * Defence in depth: even a caller that skips the service lock cannot write a
     * second capture for the same gateway transaction.
     */
    public function test_database_rejects_a_duplicate_capture_row(): void
    {
        $order = $this->placeVNPayOrder();
        $row = [
            'order_id' => $order->id, 'success' => true, 'type' => 'capture',
            'driver' => 'vnpay', 'amount' => 100, 'reference' => 'DUP1',
            'status' => '00', 'card_type' => '', 'last_four' => '',
        ];

        Transaction::create($row);

        $this->expectException(QueryException::class);
        Transaction::create($row);
    }

    /** Partial refunds legitimately reuse `refund-{id}`; the index must not block them. */
    public function test_database_allows_repeated_refund_references(): void
    {
        $order = $this->placeVNPayOrder();
        $row = [
            'order_id' => $order->id, 'success' => true, 'type' => 'refund',
            'driver' => 'vnpay', 'amount' => 50, 'reference' => 'refund-'.$order->id,
            'status' => 'refunded', 'card_type' => '', 'last_four' => '',
        ];

        Transaction::create($row);
        Transaction::create($row);

        $this->assertSame(2, Transaction::where('order_id', $order->id)->where('type', 'refund')->count());
    }
}
