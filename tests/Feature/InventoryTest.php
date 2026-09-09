<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\ProductVariant;
use Modules\Inventory\Mail\BackInStockMail;
use Modules\Inventory\Models\StockNotification;
use Modules\Inventory\Services\InventoryService;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * Inventory: stock is reserved on order placement (with an oversell guard for
 * in_stock variants), and "notify me" subscribers are emailed on restock.
 */
class InventoryTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    /** Drive the cart to an order for the given variant + quantity. */
    private function placeOrder(ProductVariant $sku, int $quantity): TestResponse
    {
        $this->postJson('/api/v1/cart', ['sku_id' => $sku->id, 'quantity' => $quantity]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        return $this->postJson('/api/v1/checkout', ['payment_type' => 'cod']);
    }

    public function test_placing_an_order_commits_stock_without_emptying_the_shelf(): void
    {
        $product = $this->createProduct(['stock' => 10]);
        $variant = $product->variants->first();

        $this->placeOrder($variant, 3)->assertSuccessful();

        $fresh = $variant->fresh();

        // The goods have not shipped, so they are still in the stockroom …
        $this->assertSame(10, (int) $fresh->stock_on_hand, 'on-hand must not drop before dispatch');
        // … but they are spoken for, so they are no longer sellable.
        $this->assertSame(3, (int) $fresh->stock_committed);
        $this->assertSame(7, $fresh->getTotalInventory());
    }

    public function test_dispatching_takes_the_committed_units_off_the_shelf(): void
    {
        $product = $this->createProduct(['stock' => 10]);
        $variant = $product->variants->first();

        $this->placeOrder($variant, 3)->assertSuccessful();

        $this->moveOrderTo(Order::latest('id')->first(), OrderStatus::DISPATCHED);

        $fresh = $variant->fresh();

        $this->assertSame(7, (int) $fresh->stock_on_hand, 'dispatch is when stock actually leaves');
        $this->assertSame(0, (int) $fresh->stock_committed);
        $this->assertSame(7, $fresh->getTotalInventory());
    }

    public function test_in_stock_variant_cannot_be_oversold(): void
    {
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();
        $variant->update(['enabled' => true]); // SKUs are always stock-tracked

        // Cart enforces a max line quantity from stock, so seed the line at the
        // limit then drop stock underneath it to simulate a concurrent sale
        // taking the last units before this order is created.
        $this->postJson('/api/v1/cart', ['sku_id' => $variant->id, 'quantity' => 2]);
        $this->setStock($variant, 1);

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        // 422, not the 500 this used to answer: somebody else took the last
        // units, which is a shopper problem with a clear message rather than a
        // server fault.
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        // Order creation rolled back → stock untouched, no order persisted.
        $this->assertSame(1, (int) $variant->fresh()->stock_on_hand);
        $this->assertDatabaseCount('lunar_orders', 0);
    }

    public function test_sku_cannot_go_negative(): void
    {
        // The flexible SKU model has no backorder mode — an order beyond stock
        // is refused, and stock is never driven negative.
        $product = $this->createProduct(['stock' => 1]);
        $variant = $product->variants->first();

        $this->postJson('/api/v1/cart', ['sku_id' => $variant->id, 'quantity' => 3])
            ->assertStatus(422);

        $this->assertSame(1, (int) $variant->fresh()->stock_on_hand);
    }

    public function test_notify_me_requires_out_of_stock_variant(): void
    {
        $product = $this->createProduct(['stock' => 5]);
        $variant = $product->variants->first();

        // In stock → nothing to wait for.
        $this->postJson('/api/v1/inventory/notify-me', [
            'sku_id' => $variant->id,
            'email' => 'shopper@example.com',
        ])->assertStatus(422);

        $this->assertDatabaseCount('stock_notifications', 0);
    }

    public function test_notify_me_allows_a_zero_stock_sku(): void
    {
        // A stock=0 SKU shows "Hết hàng" on the storefront, so the shopper must
        // be able to subscribe to be notified when it is restocked.
        $product = $this->createProduct(['stock' => 0]);
        $variant = $product->variants->first();

        $this->postJson('/api/v1/inventory/notify-me', [
            'sku_id' => $variant->id,
            'email' => 'shopper@example.com',
        ])->assertCreated();

        $this->assertDatabaseHas('stock_notifications', [
            'product_variant_id' => $variant->id,
            'email' => 'shopper@example.com',
        ]);
    }

    public function test_notify_me_subscription_is_idempotent(): void
    {
        $product = $this->createProduct(['stock' => 0]);
        $variant = $product->variants->first();
        $variant->update(['enabled' => true]); // SKUs are always stock-tracked

        $payload = ['sku_id' => $variant->id, 'email' => 'Shopper@Example.com'];

        $this->postJson('/api/v1/inventory/notify-me', $payload)->assertCreated();
        $this->postJson('/api/v1/inventory/notify-me', $payload)->assertCreated();

        // Stored once, lowercased.
        $this->assertDatabaseCount('stock_notifications', 1);
        $this->assertDatabaseHas('stock_notifications', [
            'product_variant_id' => $variant->id,
            'email' => 'shopper@example.com',
        ]);
    }

    public function test_restocking_emails_pending_subscribers_and_marks_them_notified(): void
    {
        Mail::fake();

        $product = $this->createProduct(['stock' => 0]);
        $variant = $product->variants->first();

        StockNotification::create([
            'product_variant_id' => $variant->id,
            'email' => 'waiting@example.com',
        ]);

        // Admin tops up stock (0 → 8).
        $this->setStock($variant, 8);

        Mail::assertQueued(BackInStockMail::class, fn ($mail) => $mail->hasTo('waiting@example.com'));

        $this->assertNotNull(StockNotification::first()->notified_at);
    }

    public function test_restock_does_not_renotify_already_notified_subscribers(): void
    {
        Mail::fake();

        $product = $this->createProduct(['stock' => 0]);
        $variant = $product->variants->first();

        StockNotification::create([
            'product_variant_id' => $variant->id,
            'email' => 'waiting@example.com',
            'notified_at' => now(),
        ]);

        $this->setStock($variant, 8);

        Mail::assertNothingQueued();
    }

    public function test_available_reflects_sku_quantity(): void
    {
        $service = app(InventoryService::class);

        $product = $this->createProduct(['stock' => 4]);
        $variant = $product->variants->first();

        // A SKU's available inventory is simply its on-hand quantity (no
        // backorder/always modes).
        $this->assertSame(4, $service->available($variant->id));
        $this->assertTrue($service->inStock($variant->id, 4));
        $this->assertFalse($service->inStock($variant->id, 5));

        $this->setStock($variant, 10);
        $this->assertSame(10, $service->available($variant->id));
        $this->assertTrue($service->inStock($variant->id, 9));
    }
}
