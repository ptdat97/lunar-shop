<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The free-shipping progress strip (roadmap §13).
 *
 * The backend contract (CartResource::freeShippingInfo) was already there —
 * `qualified`, `threshold`, `remaining`, `progress` — and the strip is theme
 * work. What PHPUnit can still pin: the contract shape stays useful to the JS
 * helper, the strip is disabled when the threshold is, and the cart page
 * renders the hook the enhancer fills in.
 */
class FreeShippingProgressTest extends TestCase
{
    use CreatesStorefrontData;

    protected function enableThreshold(int $threshold): void
    {
        // Settings falls back to config when the group is not stored in
        // app_settings (dev/test DB), so configuring the file value is enough.
        config(['shipping.free_threshold' => $threshold]);
    }

    public function test_progress_tracks_subtotal_below_threshold(): void
    {
        $this->enableThreshold(10_000); // $100
        $product = $this->createProduct(['price' => 2_500]); // $25
        $variantId = $product->variants->first()->id;

        $this->postJson('/api/v1/cart', ['sku_id' => $variantId, 'quantity' => 2])
            ->assertSuccessful()
            ->assertJsonPath('data.free_shipping.qualified', false)
            ->assertJsonPath('data.free_shipping.progress', 50) // 5000 / 10000
            ->assertJsonPath('data.free_shipping.remaining', '$50.00');
    }

    public function test_qualified_once_subtotal_reaches_threshold(): void
    {
        $this->enableThreshold(10_000);
        $product = $this->createProduct(['price' => 2_500]);
        $variantId = $product->variants->first()->id;

        $this->postJson('/api/v1/cart', ['sku_id' => $variantId, 'quantity' => 4])
            ->assertSuccessful()
            ->assertJsonPath('data.free_shipping.qualified', true)
            ->assertJsonPath('data.free_shipping.progress', 100)
            ->assertJsonPath('data.free_shipping.remaining', '$0.00');
    }

    public function test_strip_is_null_when_threshold_is_disabled(): void
    {
        $this->enableThreshold(0);
        $product = $this->createProduct(['price' => 25_000]);
        $variantId = $product->variants->first()->id;

        $this->postJson('/api/v1/cart', ['sku_id' => $variantId, 'quantity' => 1])
            ->assertSuccessful()
            ->assertJsonPath('data.free_shipping', null);
    }

    /** The cart page ships the SSR hook the enhancer fills in. */
    public function test_cart_page_renders_the_strip_hook(): void
    {
        $this->enableThreshold(10_000);

        $this->get('/cart')
            ->assertOk()
            ->assertSee('data-cart-shipping', false);
    }
}
