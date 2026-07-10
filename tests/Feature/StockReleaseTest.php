<?php

namespace Tests\Feature;

use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Modules\Inventory\Services\StockReleaser;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Reserved stock comes back when an order will never ship.
 *
 * `DecrementStock` reserves inventory the moment the order row is created — and
 * for a gateway payment that happens *before* the customer pays. Nothing ever
 * gave it back: an abandoned VNPay checkout, a failed payment, a cancellation
 * and a refund all destroyed the units (measured: stock 5 → 3 with nobody having
 * paid).
 */
class StockReleaseTest extends TestCase
{
    use CreatesStorefrontData;

    /** Place a COD order for 2 units of a product stocked at 5. */
    private function placeOrder(int $stock = 5, int $quantity = 2): Order
    {
        $product = $this->createProduct(['stock' => $stock]);

        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => $quantity,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        return Order::latest('id')->first();
    }

    private function stock(): int
    {
        return (int) ProductVariant::first()->stock;
    }

    public function test_placing_an_order_still_reserves_stock(): void
    {
        $this->seedBaseData();
        $this->placeOrder();

        $this->assertSame(3, $this->stock());
    }

    public function test_cancelling_an_order_returns_its_stock(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $order->update(['status' => 'cancelled']);

        $this->assertSame(5, $this->stock());
        $this->assertNotNull($order->fresh()->stock_released_at);
    }

    public function test_refunding_an_order_returns_its_stock(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $order->update(['status' => 'refunded']);

        $this->assertSame(5, $this->stock());
    }

    public function test_the_release_is_idempotent(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $order->update(['status' => 'cancelled']);
        $order->fresh()->update(['status' => 'refunded']);

        // Restocking twice would invent inventory that was never sold.
        $this->assertSame(5, $this->stock());
    }

    public function test_releasing_twice_directly_is_a_no_op(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();
        $releaser = app(StockReleaser::class);

        $this->assertTrue($releaser->release($order));
        $this->assertFalse($releaser->release($order->fresh()));
        $this->assertSame(5, $this->stock());
    }

    public function test_dispatching_an_order_does_not_return_stock(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $order->update(['status' => 'dispatched']);

        // The goods are on their way out; the reservation stands.
        $this->assertSame(3, $this->stock());
        $this->assertNull($order->fresh()->stock_released_at);
    }

    public function test_an_abandoned_gateway_order_is_expired_and_restocked(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        // A VNPay order sits in `awaiting-payment` with `meta.payment_type` set.
        $order->forceFill([
            'status' => 'awaiting-payment',
            'meta' => ['payment_type' => 'vnpay'],
            'created_at' => now()->subHours(3),
        ])->saveQuietly();

        $this->artisan('orders:expire-abandoned --minutes=60')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $this->stock());
    }

    public function test_a_recent_gateway_order_is_left_alone(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $order->forceFill([
            'status' => 'awaiting-payment',
            'meta' => ['payment_type' => 'vnpay'],
            'created_at' => now()->subMinutes(5),
        ])->saveQuietly();

        // The shopper may still be on the gateway's page.
        $this->artisan('orders:expire-abandoned --minutes=60')->assertSuccessful();

        $this->assertSame('awaiting-payment', $order->fresh()->status);
        $this->assertSame(3, $this->stock());
    }

    public function test_a_bank_transfer_is_never_expired_by_the_timer(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        // Bank transfer sits in the same status but is settled by hand; Lunar's
        // offline driver stamps no `payment_type`.
        $order->forceFill([
            'status' => 'awaiting-payment',
            'meta' => [],
            'created_at' => now()->subDays(3),
        ])->saveQuietly();

        $this->artisan('orders:expire-abandoned --minutes=60')->assertSuccessful();

        $this->assertSame('awaiting-payment', $order->fresh()->status);
        $this->assertSame(3, $this->stock(), 'the money may still be on its way');
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $order->forceFill([
            'status' => 'awaiting-payment',
            'meta' => ['payment_type' => 'momo'],
            'created_at' => now()->subDay(),
        ])->saveQuietly();

        $this->artisan('orders:expire-abandoned --minutes=60 --dry-run')->assertSuccessful();

        $this->assertSame('awaiting-payment', $order->fresh()->status);
        $this->assertSame(3, $this->stock());
    }
}
