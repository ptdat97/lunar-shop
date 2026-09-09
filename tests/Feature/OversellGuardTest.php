<?php

namespace Tests\Feature;

use Lunar\Core\Models\Order;
use Lunar\Core\Models\ProductVariant;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * A SKU cannot be sold below zero.
 *
 * The flexible SKU model tracks a plain on-hand `quantity` with no
 * backorder/always modes — a fashion shop sells what it has. Both oversell
 * guards (DecrementStock's conditional UPDATE, and CartService's check) enforce
 * this unconditionally, so a SKU can never go negative through checkout.
 */
class OversellGuardTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_a_new_sku_can_be_fulfilled_up_to_its_quantity(): void
    {
        $this->seedBaseData();

        $sku = $this->createProduct(['stock' => 5])->variants->first();

        $this->assertTrue($sku->canBeFulfilledAtQuantity(5));
        $this->assertFalse($sku->canBeFulfilledAtQuantity(6));
    }

    public function test_stock_can_never_go_negative_through_checkout(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();

        // The cart refuses the quantity outright.
        $this->postJson('/api/v1/cart', ['sku_id' => $variant->id, 'quantity' => 10])
            ->assertStatus(422);

        $this->assertSame(2, (int) ProductVariant::find($variant->id)->stock_on_hand);
        $this->assertSame(0, Order::count());
    }

    public function test_a_full_stock_order_leaves_exactly_zero(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();

        $this->postJson('/api/v1/cart', ['sku_id' => $variant->id, 'quantity' => 2])->assertSuccessful();
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        // Buying the last units leaves nothing sellable, even though they are
        // still physically on the shelf until the order is dispatched.
        $fresh = ProductVariant::find($variant->id);
        $this->assertSame(0, $fresh->getTotalInventory());
        $this->assertSame(2, (int) $fresh->stock_committed);
    }

    public function test_the_pipeline_still_guards_when_stock_vanishes_after_the_cart_check(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();

        $this->postJson('/api/v1/cart', ['sku_id' => $variant->id, 'quantity' => 2])->assertSuccessful();
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        // Someone else bought the last units while this cart sat there.
        $this->setStock($variant, 0);

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertStatus(422);

        // The conditional UPDATE in DecrementStock is the last line of defence,
        // and order creation rolls back with it.
        $this->assertSame(0, (int) ProductVariant::find($variant->id)->stock_on_hand);
        $this->assertSame(0, Order::count());
    }

    public function test_a_sku_cannot_be_oversold_even_by_a_large_order(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 1]);
        $variant = $product->variants->first();

        // No backorder mode in the SKU model: the cart refuses the quantity.
        $this->postJson('/api/v1/cart', ['sku_id' => $variant->id, 'quantity' => 5])->assertStatus(422);
        $this->assertSame(0, Order::count());

        // Stock is untouched.
        $this->assertSame(1, (int) ProductVariant::find($variant->id)->stock_on_hand);
    }
}
