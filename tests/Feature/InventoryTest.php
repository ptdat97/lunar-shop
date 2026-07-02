<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Lunar\Models\ProductVariant;
use Modules\Inventory\Mail\BackInStockMail;
use Modules\Inventory\Models\StockNotification;
use Modules\Inventory\Services\InventoryService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Inventory: stock is reserved on order placement (with an oversell guard for
 * in_stock variants), and "notify me" subscribers are emailed on restock.
 */
class InventoryTest extends TestCase
{
    use CreatesStorefrontData;

    /** Drive the cart to an order for the given variant + quantity. */
    private function placeOrder(ProductVariant $variant, int $quantity): \Illuminate\Testing\TestResponse
    {
        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => $quantity]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        return $this->postJson('/api/v1/checkout', ['payment_type' => 'cod']);
    }

    public function test_placing_an_order_decrements_stock(): void
    {
        $product = $this->createProduct(['stock' => 10]);
        $variant = $product->variants->first();

        $this->placeOrder($variant, 3)->assertSuccessful();

        $this->assertSame(7, (int) $variant->fresh()->stock);
    }

    public function test_in_stock_variant_cannot_be_oversold(): void
    {
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();
        $variant->update(['purchasable' => 'in_stock']);

        // Cart enforces a max line quantity from stock, so seed the line at the
        // limit then drop stock underneath it to simulate a concurrent sale
        // taking the last units before this order is created.
        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2]);
        $variant->update(['stock' => 1]);

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertStatus(500);

        // Order creation rolled back → stock untouched, no order persisted.
        $this->assertSame(1, (int) $variant->fresh()->stock);
        $this->assertDatabaseCount('lunar_orders', 0);
    }

    public function test_backorder_variant_may_go_negative(): void
    {
        $product = $this->createProduct(['stock' => 1]);
        $variant = $product->variants->first();
        $variant->update(['purchasable' => 'backorder', 'backorder' => 100]);

        $this->placeOrder($variant, 3)->assertSuccessful();

        $this->assertSame(-2, (int) $variant->fresh()->stock);
    }

    public function test_notify_me_requires_out_of_stock_variant(): void
    {
        $product = $this->createProduct(['stock' => 5]);
        $variant = $product->variants->first();

        // In stock → nothing to wait for.
        $this->postJson('/api/v1/inventory/notify-me', [
            'variant_id' => $variant->id,
            'email' => 'shopper@example.com',
        ])->assertStatus(422);

        $this->assertDatabaseCount('stock_notifications', 0);
    }

    public function test_notify_me_allows_backorder_variant_with_zero_stock(): void
    {
        // Regression: a stock=0 "always"/backorder variant still shows "Hết hàng"
        // on the storefront, so the shopper must be able to subscribe — even
        // though Lunar's canBeFulfilledAtQuantity() reports it as purchasable.
        $product = $this->createProduct(['stock' => 0]);
        $variant = $product->variants->first();
        $variant->update(['purchasable' => 'always']);

        $this->postJson('/api/v1/inventory/notify-me', [
            'variant_id' => $variant->id,
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
        $variant->update(['purchasable' => 'in_stock']);

        $payload = ['variant_id' => $variant->id, 'email' => 'Shopper@Example.com'];

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
        $variant->update(['stock' => 8]);

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

        $variant->update(['stock' => 8]);

        Mail::assertNothingQueued();
    }

    public function test_available_honours_purchasable_mode(): void
    {
        $service = app(InventoryService::class);

        $product = $this->createProduct(['stock' => 4]);
        $variant = $product->variants->first();

        $variant->update(['purchasable' => 'in_stock']);
        $this->assertSame(4, $service->available($variant->id));

        $variant->update(['purchasable' => 'backorder', 'backorder' => 6]);
        $this->assertSame(10, $service->available($variant->id));

        $this->assertTrue($service->inStock($variant->id, 9));
    }
}
