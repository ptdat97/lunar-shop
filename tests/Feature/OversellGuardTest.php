<?php

namespace Tests\Feature;

use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * A variant cannot be sold below zero.
 *
 * `lunar_product_variants.purchasable` defaults to `always` in Lunar's own
 * migration — "sell it whether or not we hold any" — and nothing here ever set
 * it, so all 66 variants in the database were backorder variants. Both oversell
 * guards (DecrementStock's conditional UPDATE, and CartService's check) exempt
 * `backorder`/`always` by design, so neither ever fired.
 *
 * Measured before the fix: stock 2, order 10 → checkout 200 OK, stock −8.
 */
class OversellGuardTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_a_new_variant_defaults_to_in_stock(): void
    {
        $this->seedBaseData();

        $variant = $this->createProduct(['stock' => 5])->variants->first();

        // A fashion shop sells what it has; backorder is an explicit choice.
        $this->assertSame('in_stock', $variant->purchasable);
    }

    public function test_stock_can_never_go_negative_through_checkout(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();

        // The cart refuses the quantity outright.
        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 10])
            ->assertStatus(422);

        $this->assertSame(2, (int) ProductVariant::find($variant->id)->stock);
        $this->assertSame(0, Order::count());
    }

    public function test_a_full_stock_order_leaves_exactly_zero(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();

        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2])->assertSuccessful();
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $this->assertSame(0, (int) ProductVariant::find($variant->id)->stock);
    }

    public function test_the_pipeline_still_guards_when_stock_vanishes_after_the_cart_check(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->variants->first();

        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 2])->assertSuccessful();
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        // Someone else bought the last units while this cart sat there.
        $variant->update(['stock' => 0]);

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertStatus(422);

        // The conditional UPDATE in DecrementStock is the last line of defence,
        // and order creation rolls back with it.
        $this->assertSame(0, (int) ProductVariant::find($variant->id)->stock);
        $this->assertSame(0, Order::count());
    }

    public function test_an_explicit_backorder_variant_may_still_go_negative(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 1]);
        $variant = $product->variants->first();

        // The admin chose to sell ahead of delivery: that is what the mode means.
        $variant->update(['purchasable' => 'always']);

        $this->postJson('/api/v1/cart', ['variant_id' => $variant->id, 'quantity' => 5])->assertSuccessful();
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $this->assertSame(-4, (int) ProductVariant::find($variant->id)->stock);
    }
}
