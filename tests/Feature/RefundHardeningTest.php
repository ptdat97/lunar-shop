<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Transaction;
use Modules\Checkout\Services\RefundService;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Services\ReturnService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Money leaves the building here. Probes for double-refund and for refund rows
 * that cannot be told apart during reconciliation.
 */
class RefundHardeningTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payment.vnpay.tmn_code' => 'C',
            'payment.vnpay.hash_secret' => 'S',
            'payment.vnpay.api_url' => 'https://vnpay.test/api',
        ]);
    }

    private function paidOrder(): Order
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => 'payment-received',
            'reference' => 'RMA-'.uniqid(),
            'sub_total' => 100000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 100000,
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id, 'type' => 'physical', 'description' => 'Tee',
            'quantity' => 2, 'unit_price' => 50000, 'unit_quantity' => 1,
            'sub_total' => 100000, 'discount_total' => 0, 'tax_total' => 0, 'total' => 100000,
        ]);

        OrderAddress::factory()->create([
            'order_id' => $order->id, 'type' => 'shipping',
            'first_name' => 'Mai', 'last_name' => 'Nguyen',
            'contact_email' => 'mai@example.com', 'line_one' => '1 St', 'city' => 'Hanoi',
        ]);

        Transaction::create([
            'order_id' => $order->id, 'success' => true, 'type' => 'capture',
            'driver' => 'vnpay', 'amount' => 100000, 'reference' => 'CAP', 'status' => '00',
            'card_type' => '', 'last_four' => '', 'captured_at' => now(),
            'meta' => ['vnp_TransactionNo' => '111'],
        ]);

        return $order->fresh('lines');
    }

    /** HOLE: refunding an already-refunded RMA calls the gateway a second time. */
    public function test_refunding_an_already_refunded_request_is_rejected(): void
    {
        Http::fake(['*' => Http::response(['vnp_ResponseCode' => '00', 'vnp_TransactionNo' => 'RF'], 200)]);
        Mail::fake();
        $this->seedBaseData();
        $order = $this->paidOrder();
        $line = $order->lines->first();

        $service = app(ReturnService::class);
        $request = $service->open($order, [['order_line_id' => $line->id, 'quantity' => 2]], 'defect');
        $request = $service->approve($request, refund: true);
        $this->assertSame(ReturnRequest::REFUNDED, $request->status);

        // A second click on "Refund" for the same RMA.
        try {
            $service->refund($request->fresh());
        } catch (\RuntimeException) {
            // acceptable: refused loudly
        }

        $this->assertSame(
            1,
            Transaction::where('order_id', $order->id)->where('type', 'refund')->count(),
            'the gateway must not be charged twice for one RMA'
        );
        $this->assertSame(
            100000,
            (int) Transaction::where('order_id', $order->id)->where('type', 'refund')->sum('amount'),
            'total refunded must never exceed the captured amount'
        );
    }

    /**
     * HOLE: a COD/bank order has no gateway capture, so `ReturnService::refund()`
     * skips RefundService entirely — and RefundService's ceiling with it.
     */
    public function test_offline_order_cannot_be_refunded_twice_on_the_same_request(): void
    {
        Mail::fake();
        $this->seedBaseData();
        $order = $this->paidOrder();
        // Offline settlement: no gateway capture exists.
        Transaction::where('order_id', $order->id)->delete();

        $service = app(ReturnService::class);
        $line = $order->lines->first();
        $request = $service->open($order, [['order_line_id' => $line->id, 'quantity' => 2]], 'defect');
        $request = $service->approve($request, refund: true);
        $this->assertSame(ReturnRequest::REFUNDED, $request->status);
        $this->assertSame(100000, $request->refund_amount);

        Mail::fake(); // reset: only count what the SECOND call sends

        // Second click. Nothing on the offline path has a ceiling.
        $refused = false;
        try {
            $service->refund($request->fresh());
        } catch (\RuntimeException) {
            $refused = true;
        }

        $this->assertTrue($refused, 'a second refund on a REFUNDED request must be refused');
        Mail::assertNothingQueued();
    }

    /**
     * The request is claimed (REFUNDED) before the gateway is called, so a failing
     * gateway must release the claim — otherwise a transient outage would strand
     * the RMA in REFUNDED with no money ever sent.
     */
    public function test_failed_gateway_refund_releases_the_claim_for_retry(): void
    {
        // First call declines, the retry succeeds.
        Http::fakeSequence()
            ->push(['vnp_ResponseCode' => '99', 'vnp_Message' => 'Declined'], 200)
            ->push(['vnp_ResponseCode' => '00', 'vnp_TransactionNo' => 'RF'], 200);
        Mail::fake();
        $this->seedBaseData();
        $order = $this->paidOrder();
        $line = $order->lines->first();

        $service = app(ReturnService::class);
        $request = $service->open($order, [['order_line_id' => $line->id, 'quantity' => 2]], 'defect');

        try {
            $service->approve($request, refund: true);
            $this->fail('a declined gateway refund must throw');
        } catch (\RuntimeException) {
        }

        $fresh = $request->fresh();
        $this->assertSame(ReturnRequest::APPROVED, $fresh->status, 'claim released for retry');
        $this->assertNull($fresh->refund_amount);
        $this->assertSame(0, Transaction::where('order_id', $order->id)->where('type', 'refund')->count());

        // And the retry succeeds once the gateway recovers.
        $retried = $service->refund($fresh);

        $this->assertSame(ReturnRequest::REFUNDED, $retried->status);
        $this->assertSame(1, Transaction::where('order_id', $order->id)->where('type', 'refund')->count());
    }

    /** HOLE: two partial refunds with no gateway reference collide on `refund-{id}`. */
    public function test_partial_refunds_without_gateway_reference_are_distinguishable(): void
    {
        // Gateway succeeds but returns no vnp_TransactionNo → RefundService falls back.
        Http::fake(['*' => Http::response(['vnp_ResponseCode' => '00'], 200)]);
        $this->seedBaseData();
        $order = $this->paidOrder();

        $refunds = app(RefundService::class);
        $this->assertTrue($refunds->refund($order, 30000, 'admin')->success);
        $this->assertTrue($refunds->refund($order, 40000, 'admin')->success);

        $refs = Transaction::where('order_id', $order->id)
            ->where('type', 'refund')->pluck('reference');

        $this->assertCount(2, $refs);
        $this->assertCount(2, $refs->unique(), "two refunds share reference [{$refs->first()}]");

        // Unique is not enough — the reference must still lead back to the order.
        foreach ($refs as $ref) {
            $this->assertStringStartsWith("refund-{$order->id}-", $ref);
        }
    }
}
