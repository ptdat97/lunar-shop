<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Transaction;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Mail\ReturnStatusMail;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Services\ReturnService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Return / RMA workflow: open (storefront), proration, approve+refund, reject,
 * ownership, and status emails.
 */
class ReturnRequestTest extends TestCase
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

    /** A paid VNPay order owned by $user, with one 2-qty line + capture. */
    private function paidOrder(?User $user = null): Order
    {
        $customerId = $user
            ? app(CustomerResolver::class)->forUser($user)->id
            : null;

        $order = Order::factory()->create([
            'channel_id' => \Lunar\Models\Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'customer_id' => $customerId,
            'status' => 'payment-received',
            'reference' => 'RMA-' . uniqid(),
            'sub_total' => 100000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 100000,
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id, 'type' => 'physical', 'description' => 'Tee',
            'quantity' => 2, 'unit_price' => 50000, 'unit_quantity' => 1,
            'sub_total' => 100000, 'discount_total' => 0, 'tax_total' => 0, 'total' => 100000,
        ]);

        // A shipping address gives the status email a recipient.
        \Lunar\Models\OrderAddress::factory()->create([
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

    public function test_open_prorates_refundable_amount_by_quantity(): void
    {
        Mail::fake();
        $this->seedBaseData();
        $order = $this->paidOrder();
        $line = $order->lines->first();

        $service = app(ReturnService::class);
        $request = $service->open($order, [['order_line_id' => $line->id, 'quantity' => 1]], 'wrong-size');

        $this->assertSame(ReturnRequest::REQUESTED, $request->status);
        // 1 of 2 units of a 100000 line → 50000.
        $this->assertSame(50000, $service->refundableAmount($request));
        Mail::assertQueued(ReturnStatusMail::class);
    }

    public function test_approve_with_refund_marks_refunded_and_records_transaction(): void
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
        $this->assertSame(100000, $request->refund_amount);
        $this->assertDatabaseHas('lunar_transactions', [
            'order_id' => $order->id, 'type' => 'refund', 'driver' => 'vnpay',
        ]);
    }

    public function test_reject_sets_status_and_emails(): void
    {
        Mail::fake();
        $this->seedBaseData();
        $order = $this->paidOrder();
        $line = $order->lines->first();

        $service = app(ReturnService::class);
        $request = $service->open($order, [['order_line_id' => $line->id, 'quantity' => 1]], 'changed-mind');
        $request = $service->reject($request, 'Outside return window');

        $this->assertSame(ReturnRequest::REJECTED, $request->status);
        $this->assertSame('Outside return window', $request->staff_note);
        Mail::assertQueued(ReturnStatusMail::class, 2); // open + reject
    }

    public function test_open_rejects_invalid_quantities(): void
    {
        $this->seedBaseData();
        $order = $this->paidOrder();
        $line = $order->lines->first();

        $this->expectException(\InvalidArgumentException::class);
        // Quantity 3 exceeds the line's quantity of 2.
        app(ReturnService::class)->open($order, [['order_line_id' => $line->id, 'quantity' => 3]], 'x');
    }

    public function test_storefront_owner_can_open_return(): void
    {
        Mail::fake();
        $this->seedBaseData();
        $user = $this->createUser();
        $order = $this->paidOrder($user);
        $line = $order->lines->first();

        $this->actingAs($user)
            ->postJson("/account/orders/{$order->id}/returns", [
                'reason' => 'wrong-size',
                'lines' => [['order_line_id' => $line->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ReturnRequest::REQUESTED);

        $this->assertDatabaseHas('return_requests', ['order_id' => $order->id]);
    }

    public function test_storefront_non_owner_gets_404(): void
    {
        $this->seedBaseData();
        $owner = $this->createUser();
        $order = $this->paidOrder($owner);
        $line = $order->lines->first();
        $intruder = $this->createUser();

        $this->actingAs($intruder)
            ->postJson("/account/orders/{$order->id}/returns", [
                'reason' => 'x',
                'lines' => [['order_line_id' => $line->id, 'quantity' => 1]],
            ])
            ->assertNotFound();
    }
}
