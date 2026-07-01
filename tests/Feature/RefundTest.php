<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Modules\Checkout\Services\RefundService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Gateway refunds (VNPay + MoMo): call the refund API (faked), record a `refund`
 * Transaction, and move a fully-refunded order to `refunded`. Partial refunds
 * keep the order paid and reduce the refundable balance.
 */
class RefundTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payment.vnpay.tmn_code' => 'TESTCODE',
            'payment.vnpay.hash_secret' => 'SECRET',
            'payment.vnpay.api_url' => 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction',
            'payment.momo.partner_code' => 'MOMOTEST',
            'payment.momo.access_key' => 'AK',
            'payment.momo.secret_key' => 'SK',
            'payment.momo.refund_url' => 'https://test-payment.momo.vn/v2/gateway/api/refund',
        ]);
    }

    /** A paid order with a successful capture transaction for the given driver. */
    private function paidOrder(string $driver, int $total = 100000): Order
    {
        $order = Order::factory()->create([
            'channel_id' => \Lunar\Models\Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => 'payment-received',
            'reference' => 'PAID-' . strtoupper($driver),
            'sub_total' => $total,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => $total,
        ]);

        Transaction::create([
            'order_id' => $order->id,
            'success' => true,
            'type' => 'capture',
            'driver' => $driver,
            'amount' => $total,
            'reference' => 'CAP123',
            'status' => '00',
            'card_type' => '',
            'last_four' => '',
            'captured_at' => now(),
            'meta' => ['vnp_TransactionNo' => '999', 'transId' => '888'],
        ]);

        return $order->fresh();
    }

    public function test_vnpay_full_refund_records_transaction_and_marks_refunded(): void
    {
        Http::fake(['*' => Http::response(['vnp_ResponseCode' => '00', 'vnp_TransactionNo' => 'RF1'], 200)]);
        $order = $this->paidOrder('vnpay');

        $result = app(RefundService::class)->refund($order);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('lunar_transactions', [
            'order_id' => $order->id, 'type' => 'refund', 'driver' => 'vnpay', 'success' => true,
        ]);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_momo_full_refund_records_transaction_and_marks_refunded(): void
    {
        Http::fake(['*' => Http::response(['resultCode' => 0, 'transId' => 'RF2'], 200)]);
        $order = $this->paidOrder('momo');

        $result = app(RefundService::class)->refund($order);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('lunar_transactions', [
            'order_id' => $order->id, 'type' => 'refund', 'driver' => 'momo', 'success' => true,
        ]);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_partial_refund_keeps_order_paid_and_reduces_balance(): void
    {
        Http::fake(['*' => Http::response(['vnp_ResponseCode' => '00', 'vnp_TransactionNo' => 'RF3'], 200)]);
        $order = $this->paidOrder('vnpay', 100000);
        $service = app(RefundService::class);

        $result = $service->refund($order, 40000);

        $this->assertTrue($result->success);
        $this->assertSame('payment-received', $order->fresh()->status, 'partial refund keeps order paid');
        $this->assertSame(40000, $service->refundedTotal($order->fresh()));
        $this->assertTrue($service->isRefundable($order->fresh()), 'still 60k refundable');
    }

    public function test_gateway_failure_records_nothing(): void
    {
        Http::fake(['*' => Http::response(['vnp_ResponseCode' => '99', 'vnp_Message' => 'Declined'], 200)]);
        $order = $this->paidOrder('vnpay');

        $result = app(RefundService::class)->refund($order);

        $this->assertFalse($result->success);
        $this->assertDatabaseMissing('lunar_transactions', ['order_id' => $order->id, 'type' => 'refund']);
        $this->assertSame('payment-received', $order->fresh()->status);
    }

    public function test_over_refund_is_rejected(): void
    {
        Http::fake();
        $order = $this->paidOrder('vnpay', 50000);

        $result = app(RefundService::class)->refund($order, 60000);

        $this->assertFalse($result->success);
        Http::assertNothingSent();
    }
}
