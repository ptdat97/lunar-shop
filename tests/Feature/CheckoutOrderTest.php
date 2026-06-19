<?php

namespace Tests\Feature;

use Lunar\Models\Order;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class CheckoutOrderTest extends TestCase
{
    use CreatesStorefrontData;

    /** Drive the cart up to the point an order can be placed. */
    private function primeCheckout(int $price = 5000): void
    {
        $product = $this->createProduct(['price' => $price]);
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->getJson('/api/v1/checkout/shipping-options')
            ->assertOk()
            ->assertJsonPath('data.0.identifier', 'standard');
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
    }

    public function test_place_cod_order(): void
    {
        $this->primeCheckout();

        $res = $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertSuccessful()
            ->assertJsonStructure(['data' => ['id', 'reference', 'status']]);

        $this->assertDatabaseHas('lunar_orders', [
            'reference' => $res->json('data.reference'),
            'status' => 'payment-offline',
        ]);
    }

    public function test_place_rejects_unknown_payment_type(): void
    {
        $this->primeCheckout();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'bitcoin'])
            ->assertStatus(422)->assertJsonValidationErrorFor('payment_type');
    }

    public function test_authenticated_order_appears_in_history_and_detail(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->primeCheckout(7000);
        $reference = $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->json('data.reference');

        // Order history (clean Resource shape).
        $this->getJson('/api/v1/customer/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', $reference);

        $orderId = Order::where('reference', $reference)->value('id');

        $this->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonStructure(['data' => ['lines', 'shipping_address', 'total']]);
    }

    public function test_cannot_view_another_customers_order(): void
    {
        // Place an order as user A.
        $userA = $this->createUser();
        $this->actingAs($userA);
        $this->primeCheckout();
        $reference = $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->json('data.reference');
        $orderId = Order::where('reference', $reference)->value('id');

        // User B must not see it.
        $userB = $this->createUser();
        $this->actingAs($userB)->getJson("/api/v1/orders/{$orderId}")->assertNotFound();
    }
}
