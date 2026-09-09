<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderAddress;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\Staff;
use Lunar\Core\Models\Transaction;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Services\ReturnService;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * The returns queue on the panel.
 *
 * This screen is where the resource engine stops being forms-over-data: a
 * return is opened by a customer and worked by staff, so what matters is not
 * editing fields but which operations a row offers and whether the server
 * agrees to run them.
 */
class PanelReturnQueueTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.vnpay.tmn_code' => 'C',
            'payment.vnpay.hash_secret' => 'S',
            'payment.vnpay.api_url' => 'https://vnpay.test/api',
        ]);

        Mail::fake();
        $this->seedBaseData();
    }

    private function actingAsAdmin(): static
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        return $this;
    }

    private function pendingReturn(): ReturnRequest
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            ...$this->orderAttributesFor(OrderStatus::PAYMENT_RECEIVED),
            'reference' => 'RMA-'.uniqid(),
            'sub_total' => 100000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 100000,
        ]);

        $line = OrderLine::factory()->create([
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

        return app(ReturnService::class)->open(
            $order->fresh('lines'),
            [['order_line_id' => $line->id, 'quantity' => 1]],
            'wrong-size',
        );
    }

    /**
     * Which buttons a row offers comes from that row's status. RowActions.vue
     * draws an action only when the row carries a URL for it, so this is where
     * per-row permissions actually live.
     */
    public function test_a_row_only_offers_the_actions_its_status_allows(): void
    {
        $return = $this->pendingReturn();

        $this->actingAsAdmin()
            ->get(route('panel.shop.returns.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows.0._actions.approve')
                ->has('rows.0._actions.reject')
                // Refund only becomes available once approved.
                ->missing('rows.0._actions.refund')
                // A returns queue is filled by customers, never by staff.
                ->missing('rows.0._actions.destroy')
                ->where('resource.canCreate', false)
                ->where('resource.canDelete', false),
            );

        $return->update(['status' => ReturnRequest::APPROVED]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.returns.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows.0._actions.refund')
                ->missing('rows.0._actions.approve'),
            );
    }

    public function test_approving_moves_the_return_and_records_the_note(): void
    {
        $return = $this->pendingReturn();

        $this->actingAsAdmin()
            ->post(route('panel.shop.returns.action', [$return->id, 'approve']), [
                'refund' => false,
                'staff_note' => 'Đã kiểm hàng',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ReturnRequest::APPROVED, $return->fresh()->status);
        $this->assertSame('Đã kiểm hàng', $return->fresh()->staff_note);
    }

    /**
     * The action re-checks its own availability server-side. A tab left open on
     * yesterday's queue must not be able to refund a return twice.
     */
    public function test_an_action_the_row_no_longer_allows_is_refused(): void
    {
        $return = $this->pendingReturn();
        $return->update(['status' => ReturnRequest::REFUNDED]);

        $this->actingAsAdmin()
            ->post(route('panel.shop.returns.action', [$return->id, 'approve']))
            ->assertNotFound();

        $this->assertSame(ReturnRequest::REFUNDED, $return->fresh()->status);
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $return = $this->pendingReturn();

        $this->actingAsAdmin()
            ->post(route('panel.shop.returns.action', [$return->id, 'delete-everything']))
            ->assertNotFound();
    }

    /** An action's payload is validated by the rules the action declared. */
    public function test_action_input_is_validated(): void
    {
        $return = $this->pendingReturn();

        $this->actingAsAdmin()
            ->post(route('panel.shop.returns.action', [$return->id, 'approve']), [
                'staff_note' => str_repeat('x', 2100),
            ])
            ->assertSessionHasErrors('staff_note');

        $this->assertSame(ReturnRequest::REQUESTED, $return->fresh()->status);
    }

    /**
     * A refund is a gateway call that can fail for real reasons. The screen has
     * to say so rather than 500 — this is money going back out.
     */
    public function test_a_failing_refund_surfaces_its_reason_instead_of_crashing(): void
    {
        Http::fake(['*' => Http::response(['vnp_ResponseCode' => '99'], 200)]);

        $return = $this->pendingReturn();
        $return->update(['status' => ReturnRequest::APPROVED]);

        $this->actingAsAdmin()
            ->post(route('panel.shop.returns.action', [$return->id, 'refund']))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(ReturnRequest::APPROVED, $return->fresh()->status);
    }

    /** The queue is gated by the orders permission, not the content one. */
    public function test_the_queue_uses_the_sales_permission(): void
    {
        $staff = Staff::factory()->create(['admin' => false]);

        $this->actingAs($staff, 'staff')
            ->get(route('panel.shop.returns.index'))
            ->assertForbidden();

        $staff->givePermissionTo('sales:manage-orders');

        $this->actingAs($staff->fresh(), 'staff')
            ->get(route('panel.shop.returns.index'))
            ->assertOk();
    }

    /** The record sheet is read-only: no save, no delete. */
    public function test_the_form_cannot_be_saved(): void
    {
        $return = $this->pendingReturn();

        $this->actingAsAdmin()
            ->get(route('panel.shop.returns.edit', $return->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('resource.canEdit', false));

        $this->actingAsAdmin()
            ->put(route('panel.shop.returns.update', $return->id), ['reference' => 'HACKED'])
            ->assertNotFound();

        $this->actingAsAdmin()
            ->delete(route('panel.shop.returns.destroy', $return->id))
            ->assertNotFound();

        $this->assertNotSame('HACKED', $return->fresh()->reference);
    }
}
