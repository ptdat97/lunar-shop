<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Mail\OrderPaidMail;
use Modules\Promotion\Services\MembershipService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * COD orders count as paid.
 *
 * `payment-offline` (COD) has always counted as realised revenue in the sales
 * dashboard, but membership used a separate status list that omitted it and
 * OrderPaid was only dispatched from the VNPay/MoMo callbacks — so COD
 * customers never advanced a tier. These tests pin both halves of the fix, plus
 * the constraint that a COD customer must not be told "amount paid" before they
 * have handed over any money.
 */
class CodMembershipTest extends TestCase
{
    use CreatesStorefrontData;

    private function order(string $status, int $total, ?Customer $customer = null): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer?->id,
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => $status,
            'sub_total' => $total,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => $total,
        ]);
    }

    public function test_paid_statuses_have_a_single_definition(): void
    {
        // The membership-specific list is gone; everything reads analytics.
        $this->assertNull(config('promotion.membership.paid_statuses'));

        $this->assertSame(
            ['payment-offline', 'payment-received', 'dispatched', 'completed'],
            config('analytics.paid_statuses'),
        );
    }

    public function test_cod_spend_counts_toward_lifetime_spend(): void
    {
        $this->seedBaseData();
        $customer = Customer::create(['first_name' => 'Cod', 'last_name' => 'Buyer']);

        // 3,000,000 VND in minor units (factor 100).
        $this->order('payment-offline', 300_000_000, $customer);

        $this->assertSame(
            300_000_000,
            app(MembershipService::class)->lifetimeSpend($customer->fresh()),
        );
    }

    public function test_cod_customer_reaches_a_membership_tier(): void
    {
        $this->seedBaseData();
        $customer = Customer::create(['first_name' => 'Cod', 'last_name' => 'Buyer']);

        $this->order('payment-offline', 300_000_000, $customer);

        $tier = app(MembershipService::class)->syncCustomer($customer->fresh());

        $this->assertSame('member-silver', $tier['handle']);

        $silver = CustomerGroup::where('handle', 'member-silver')->first();
        $this->assertTrue($customer->fresh()->customerGroups->pluck('id')->contains($silver->id));
    }

    public function test_awaiting_payment_still_does_not_count(): void
    {
        $this->seedBaseData();
        $customer = Customer::create(['first_name' => 'Bank', 'last_name' => 'Transfer']);

        // Bank transfer authorizes to `awaiting-payment` — not yet paid.
        $this->order('awaiting-payment', 300_000_000, $customer);

        $membership = app(MembershipService::class);
        $this->assertSame(0, $membership->lifetimeSpend($customer->fresh()));
        $this->assertNull($membership->syncCustomer($customer->fresh()));
    }

    public function test_placing_a_cod_order_dispatches_order_paid(): void
    {
        $this->seedBaseData();
        Event::fake([OrderPaid::class]);

        $this->placeCodOrder();

        Event::assertDispatched(OrderPaid::class);
    }

    public function test_cod_order_does_not_send_the_amount_paid_email(): void
    {
        $this->seedBaseData();
        Mail::fake();

        $this->placeCodOrder();

        // COD money changes hands on delivery: OrderPaid fires (for spend/
        // analytics) but the "Amount paid" mail must not.
        Mail::assertNotQueued(OrderPaidMail::class);
    }

    public function test_gateway_capture_still_sends_the_amount_paid_email(): void
    {
        $this->seedBaseData();
        Mail::fake();

        // A real capture moves the order to `payment-received`.
        $order = $this->order('payment-received', 100_000);

        // OrderMailer resolves the recipient from the order's address.
        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'contact_email' => 'buyer@example.com',
        ]);

        OrderPaid::dispatch($order->fresh());

        Mail::assertQueued(OrderPaidMail::class);
    }

    /** Drive the real checkout endpoints to place a COD order. */
    private function placeCodOrder(): void
    {
        $product = $this->createProduct();

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->skus->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])
            ->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])
            ->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertSuccessful();
    }
}
