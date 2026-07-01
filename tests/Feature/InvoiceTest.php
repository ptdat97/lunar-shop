<?php

namespace Tests\Feature;

use App\Models\User;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Services\InvoiceService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Invoice PDF: generation + owner-only download route.
 */
class InvoiceTest extends TestCase
{
    use CreatesStorefrontData;

    /** An order owned by the given user (via their resolved customer). */
    private function orderFor(User $user): Order
    {
        $customer = app(CustomerResolver::class)->forUser($user);

        $order = Order::factory()->create([
            'channel_id' => \Lunar\Models\Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'customer_id' => $customer->id,
            'status' => 'payment-received',
            'reference' => 'INV-0001',
            'sub_total' => 5000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 5000,
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'type' => 'physical',
            'description' => 'Test Tee',
            'quantity' => 1,
            'unit_price' => 5000,
            'unit_quantity' => 1,
            'sub_total' => 5000,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 5000,
        ]);

        return $order->fresh(['lines']);
    }

    public function test_service_generates_a_pdf(): void
    {
        $this->seedBaseData();
        $order = $this->orderFor($this->createUser());

        $bytes = app(InvoiceService::class)->bytes($order);

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertStringContainsString('INV-0001', app(InvoiceService::class)->filename($order));
    }

    public function test_owner_can_download_invoice(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $order = $this->orderFor($user);

        $this->actingAs($user)
            ->get("/account/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_non_owner_cannot_download_invoice(): void
    {
        $this->seedBaseData();
        $order = $this->orderFor($this->createUser());
        $intruder = $this->createUser();

        $this->actingAs($intruder)
            ->get("/account/orders/{$order->id}/invoice")
            ->assertNotFound();
    }

    public function test_guest_cannot_download_invoice(): void
    {
        $this->seedBaseData();
        $order = $this->orderFor($this->createUser());

        // No login route in this app → the controller returns 404 for guests.
        $this->get("/account/orders/{$order->id}/invoice")
            ->assertNotFound();
    }
}
