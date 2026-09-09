<?php

namespace Tests\Feature;

use Lunar\Core\Models\Order;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Services\StockReleaser;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
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
    use DrivesOrderLifecycle;

    /** Place a COD order for 2 units of a product stocked at 5. */
    private function placeOrder(int $stock = 5, int $quantity = 2): Order
    {
        $product = $this->createProduct(['stock' => $stock]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->skus->first()->id,
            'quantity' => $quantity,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        return Order::latest('id')->first();
    }

    /**
     * What a shopper can still buy.
     *
     * Ordering now *commits* stock rather than decrementing it (units stay on
     * the shelf until dispatch), so these reserve/release assertions are about
     * the sellable figure — `quantity` minus what is already spoken for.
     */
    private function stock(): int
    {
        return ProductSku::first()->getTotalInventory();
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

        $this->moveOrderTo($order, OrderStatus::CANCELLED);

        $this->assertSame(5, $this->stock());
        $this->assertNotNull($order->fresh()->stock_released_at);
    }

    public function test_release_credits_the_current_sku_when_the_ordered_id_was_recreated(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder(stock: 5, quantity: 2); // stock 5 → 3

        $line = $order->lines->first();
        $code = $line->identifier;                 // durable sku string
        $this->assertNotEmpty($code);
        $productId = $line->purchasable->product_id;

        // Simulate a product edit that delete-and-recreates the SKU: the order
        // line's purchasable_id now points at a hard-deleted row, but a new SKU
        // carries the same code. (This is the state a removal path can leave.)
        // The order held a commitment, so the shelf still reads 5 with 2 of them
        // committed — that is the state the recreated row must carry over.
        ProductSku::where('sku', $code)->forceDelete();
        $recreated = ProductSku::create([
            'product_id' => $productId,
            'sku' => $code, 'variants' => [], 'quantity' => 5, 'committed' => 2, 'price' => 1999,
            'status' => 'published', 'is_default' => true,
        ]);
        $this->assertNull(ProductSku::find((int) $line->purchasable_id), 'ordered id should be gone');

        $this->moveOrderTo($order, OrderStatus::CANCELLED);

        // The 2 held units are freed on the recreated SKU via the identifier
        // fallback rather than lost: the shelf never moved, the hold is gone.
        $fresh = $recreated->fresh();
        $this->assertSame(5, (int) $fresh->quantity);
        $this->assertSame(0, (int) $fresh->committed);
        $this->assertSame(5, $fresh->getTotalInventory());
        $this->assertNotNull($order->fresh()->stock_released_at);
    }

    public function test_refunding_an_order_returns_its_stock(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $this->moveOrderTo($order, OrderStatus::REFUNDED);

        $this->assertSame(5, $this->stock());
    }

    public function test_the_release_is_idempotent(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $this->moveOrderTo($order, OrderStatus::CANCELLED);
        $this->moveOrderTo($order->fresh(), OrderStatus::REFUNDED);

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

        $this->moveOrderTo($order, OrderStatus::DISPATCHED);

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
            ...$this->orderAttributesFor(OrderStatus::AWAITING_PAYMENT, ['payment_type' => 'vnpay']),
            'created_at' => now()->subHours(3),
        ])->saveQuietly();

        $this->artisan('orders:expire-abandoned --minutes=60')->assertSuccessful();

        $this->assertSame(OrderStatus::CANCELLED, OrderStatus::of($order->fresh()));
        $this->assertSame(5, $this->stock());
    }

    public function test_a_recent_gateway_order_is_left_alone(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $order->forceFill([
            ...$this->orderAttributesFor(OrderStatus::AWAITING_PAYMENT, ['payment_type' => 'vnpay']),
            'created_at' => now()->subMinutes(5),
        ])->saveQuietly();

        // The shopper may still be on the gateway's page.
        $this->artisan('orders:expire-abandoned --minutes=60')->assertSuccessful();

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order->fresh()));
        $this->assertSame(3, $this->stock());
    }

    public function test_a_bank_transfer_is_never_expired_by_the_timer(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        // A bank transfer is awaiting payment like a gateway order, but it is
        // settled by hand and the timer must never touch it — its payment type
        // is not one of the gateways the sweep looks for.
        $order->forceFill([
            ...$this->orderAttributesFor(OrderStatus::AWAITING_PAYMENT, ['payment_type' => 'bank-transfer']),
            'created_at' => now()->subDays(3),
        ])->saveQuietly();

        $this->artisan('orders:expire-abandoned --minutes=60')->assertSuccessful();

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order->fresh()));
        $this->assertSame(3, $this->stock(), 'the money may still be on its way');
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $order->forceFill([
            ...$this->orderAttributesFor(OrderStatus::AWAITING_PAYMENT, ['payment_type' => 'momo']),
            'created_at' => now()->subDay(),
        ])->saveQuietly();

        $this->artisan('orders:expire-abandoned --minutes=60 --dry-run')->assertSuccessful();

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order->fresh()));
        $this->assertSame(3, $this->stock());
    }
}
