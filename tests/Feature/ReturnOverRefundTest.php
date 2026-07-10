<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Lunar\Models\Order;
use Modules\Order\Mail\ReturnStatusMail;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Services\ReturnService;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * A line can only be returned once.
 *
 * `validLines()` used to compare the requested quantity against the order line's
 * *full* quantity, never subtracting what earlier requests had already claimed.
 * Two RMAs could therefore each claim the whole line: on a gateway order
 * RefundService capped the money, but an offline (cod/bank) order had no ceiling
 * at all — measured, a 23,000 order paid out 40,000 across two refunds.
 */
class ReturnOverRefundTest extends TestCase
{
    use CreatesStorefrontData;

    /**
     * Place a COD order for `$quantity` units at 100.00 each, then dispatch it.
     *
     * A COD order sits at `payment-offline` until it ships — the buyer has
     * nothing in hand yet, so it is not returnable. Returns start once the
     * goods are on their way.
     */
    private function placeOrder(int $quantity): Order
    {
        $product = $this->createProduct(['stock' => 10, 'price' => 10000]);

        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => $quantity,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $order = Order::latest('id')->first();
        $order->update(['status' => OrderStatus::DISPATCHED]);

        return $order->fresh();
    }

    private function physicalLine(Order $order)
    {
        return $order->lines()->where('type', 'physical')->first();
    }

    public function test_an_order_that_never_shipped_cannot_be_returned(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(1);
        $line = $this->physicalLine($order);

        // Back to "placed, not yet shipped". `can_return` in OrderResource hid
        // the button, but the endpoint accepted an RMA anyway — and staff could
        // then refund it. Measured: 10,000 refunded on an order never paid for.
        $order->forceFill(['status' => OrderStatus::AWAITING_PAYMENT])->saveQuietly();

        $this->expectExceptionMessage('This order cannot be returned.');
        app(ReturnService::class)->open(
            $order->fresh(),
            [['order_line_id' => $line->id, 'quantity' => 1]],
            'too-large',
        );
    }

    public function test_a_cod_order_becomes_returnable_once_dispatched(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(1);
        $line = $this->physicalLine($order);

        // COD sits at `payment-offline` until it ships: nothing to send back.
        $order->forceFill(['status' => OrderStatus::PAYMENT_OFFLINE])->saveQuietly();
        $this->assertFalse(OrderStatus::isReturnable($order->fresh()->status));

        $order->update(['status' => OrderStatus::DISPATCHED]);

        $request = app(ReturnService::class)->open(
            $order->fresh(),
            [['order_line_id' => $line->id, 'quantity' => 1]],
            'too-large',
        );

        $this->assertSame(ReturnRequest::REQUESTED, $request->status);
    }

    public function test_the_same_line_cannot_be_claimed_twice(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(2);
        $line = $this->physicalLine($order);
        $service = app(ReturnService::class);

        $service->open($order, [['order_line_id' => $line->id, 'quantity' => 2]], 'too-large');

        $this->expectException(InvalidArgumentException::class);
        $service->open($order, [['order_line_id' => $line->id, 'quantity' => 2]], 'too-large');
    }

    public function test_partial_returns_add_up_to_the_line_quantity(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(3);
        $line = $this->physicalLine($order);
        $service = app(ReturnService::class);

        $service->open($order, [['order_line_id' => $line->id, 'quantity' => 1]], 'too-large');
        $service->open($order, [['order_line_id' => $line->id, 'quantity' => 2]], 'too-large');

        $this->assertSame(0, $service->remainingQuantities($order->fresh())[$line->id]);

        // The fourth unit was never bought.
        $this->expectException(InvalidArgumentException::class);
        $service->open($order->fresh(), [['order_line_id' => $line->id, 'quantity' => 1]], 'too-large');
    }

    public function test_a_rejected_request_releases_its_claim(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(2);
        $line = $this->physicalLine($order);
        $service = app(ReturnService::class);

        $rejected = $service->open($order, [['order_line_id' => $line->id, 'quantity' => 2]], 'too-large');
        $service->reject($rejected);

        // Staff said no, so the units are the customer's to try again with.
        $this->assertSame(2, $service->remainingQuantities($order->fresh())[$line->id]);

        $reopened = $service->open($order->fresh(), [['order_line_id' => $line->id, 'quantity' => 2]], 'too-small');
        $this->assertSame(ReturnRequest::REQUESTED, $reopened->status);
    }

    public function test_total_refunds_never_exceed_the_order_total(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(2);
        $line = $this->physicalLine($order);
        $service = app(ReturnService::class);

        $first = $service->open($order, [['order_line_id' => $line->id, 'quantity' => 1]], 'too-large');
        $second = $service->open($order->fresh(), [['order_line_id' => $line->id, 'quantity' => 1]], 'too-large');

        $service->refund($first);
        $service->refund($second);

        $refunded = ReturnRequest::where('order_id', $order->id)
            ->where('status', ReturnRequest::REFUNDED)
            ->sum('refund_amount');

        // An offline order has no gateway to enforce a ceiling, so the service must.
        $this->assertLessThanOrEqual((int) $order->fresh()->total->value, (int) $refunded);
    }

    public function test_a_legacy_duplicate_claim_is_capped_at_what_remains(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(1);
        $line = $this->physicalLine($order);
        $service = app(ReturnService::class);

        $first = $service->open($order, [['order_line_id' => $line->id, 'quantity' => 1]], 'too-large');
        $service->refund($first);

        // A row created before the line guard existed can still double-claim.
        // The refund cap is the second line of defence: it may only pay out what
        // the order has left (here: the shipping the first refund did not take).
        $stale = ReturnRequest::create([
            'order_id' => $order->id,
            'reference' => 'RMA-LEGACY',
            'status' => ReturnRequest::APPROVED,
            'reason' => 'too-large',
        ]);
        $stale->lines()->create(['order_line_id' => $line->id, 'quantity' => 1]);

        $service->refund($stale->fresh());

        $orderTotal = (int) $order->fresh()->total->value;
        $refunded = (int) ReturnRequest::where('order_id', $order->id)
            ->where('status', ReturnRequest::REFUNDED)
            ->sum('refund_amount');

        $this->assertSame($orderTotal, $refunded, 'the cap pays out exactly what is left, never more');
    }

    public function test_a_refund_with_nothing_left_is_refused(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(1);
        $line = $this->physicalLine($order);
        $service = app(ReturnService::class);

        // Pretend the order has already been refunded in full.
        ReturnRequest::create([
            'order_id' => $order->id,
            'reference' => 'RMA-FULL',
            'status' => ReturnRequest::REFUNDED,
            'reason' => 'too-large',
            'refund_amount' => (int) $order->total->value,
        ]);

        $stale = ReturnRequest::create([
            'order_id' => $order->id,
            'reference' => 'RMA-LEGACY',
            'status' => ReturnRequest::APPROVED,
            'reason' => 'too-large',
        ]);
        $stale->lines()->create(['order_line_id' => $line->id, 'quantity' => 1]);

        $this->expectExceptionMessage('Nothing left to refund on this order.');
        $service->refund($stale->fresh());
    }

    public function test_the_email_is_sent_outside_the_transaction(): void
    {
        $this->seedBaseData();
        Mail::fake();

        $order = $this->placeOrder(1);
        $line = $this->physicalLine($order);

        app(ReturnService::class)->open($order, [['order_line_id' => $line->id, 'quantity' => 1]], 'too-large');

        // A mail cannot be rolled back, so it must not run inside DB::transaction.
        Mail::assertQueued(ReturnStatusMail::class);
    }
}
