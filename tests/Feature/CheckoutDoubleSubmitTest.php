<?php

namespace Tests\Feature;

use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Placing an order twice must not confuse the customer or the books.
 *
 * The first submit consumes the cart, so the second one finds the fresh empty
 * cart that replaced it and used to blow up deep inside Lunar with
 * "A billing address is required" — a 500 for what is really a double-click.
 */
class CheckoutDoubleSubmitTest extends TestCase
{
    use CreatesStorefrontData;

    private function primeCheckout(int $stock = 10): void
    {
        $product = $this->createProduct(['stock' => $stock]);

        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => 2,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
    }

    public function test_a_second_submit_is_refused_without_creating_a_second_order(): void
    {
        $this->seedBaseData();
        $this->primeCheckout();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Your cart is empty.');

        $this->assertSame(1, Order::count());
    }

    public function test_the_double_submit_does_not_double_reserve_stock(): void
    {
        $this->seedBaseData();
        $this->primeCheckout(10);

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertStatus(422);

        // 10 − 2, once.
        $this->assertSame(8, (int) ProductVariant::first()->stock);
    }

    public function test_checking_out_an_empty_cart_is_refused(): void
    {
        $this->seedBaseData();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Your cart is empty.');

        $this->assertSame(0, Order::count());
    }
}
