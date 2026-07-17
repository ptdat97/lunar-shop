<?php

namespace Tests\Feature;

use Lunar\Models\Country;
use Lunar\Models\Order;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * SSR Blade checkout: one POST /checkout submits address + shipping + payment
 * together, so the old multi-step "Enter your address first" race can't happen.
 */
class CheckoutSsrTest extends TestCase
{
    use CreatesStorefrontData;

    /** @return array<string, mixed> */
    private function checkoutForm(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mai',
            'last_name' => 'Tran',
            'line_one' => '1 Le Loi',
            'state' => 'Thành phố Hồ Chí Minh',
            'city' => 'Phường Bến Nghé',
            'country_id' => Country::query()->value('id'),
            'contact_email' => 'buyer@example.com',
            'contact_phone' => '0900000000',
            'shipping_option' => 'standard',
            'payment_type' => 'cod',
        ], $overrides);
    }

    public function test_checkout_page_renders(): void
    {
        $this->seedBaseData();
        $this->seedLocations();
        $product = $this->createProduct();
        $this->postJson('/api/v1/cart', ['sku_id' => $product->skus->first()->id, 'quantity' => 1]);

        // Shopify-style two-column SSR checkout: Contact + Delivery + Shipping +
        // Payment on the left, grey order summary on the right.
        $this->get('/checkout')
            ->assertOk()
            ->assertSee('Delivery')                   // address section title
            ->assertSee('Shipping method')            // shipping section title
            ->assertSee('Standard Delivery')          // shipping option renders
            ->assertSee('Thành phố Hồ Chí Minh')      // province options are SSR
            ->assertSee('checkout-summary', false)    // order summary panel
            ->assertSee('Place order');
    }

    public function test_place_order_in_one_post(): void
    {
        $this->seedBaseData();
        $this->seedLocations();
        $product = $this->createProduct(['price' => 5000]);
        $this->postJson('/api/v1/cart', ['sku_id' => $product->skus->first()->id, 'quantity' => 1]);

        $response = $this->post('/checkout', $this->checkoutForm());

        $order = Order::latest('id')->first();
        $this->assertNotNull($order, 'order should be created');
        $response->assertRedirectContains('/checkout/confirmation/'.$order->reference);

        $this->assertDatabaseHas('lunar_orders', [
            'reference' => $order->reference,
            'status' => 'payment-offline',
        ]);
    }

    public function test_missing_address_field_is_a_validation_error(): void
    {
        $this->seedBaseData();
        $this->seedLocations();
        $product = $this->createProduct();
        $this->postJson('/api/v1/cart', ['sku_id' => $product->skus->first()->id, 'quantity' => 1]);

        $this->post('/checkout', $this->checkoutForm(['line_one' => '']))
            ->assertSessionHasErrors('line_one');

        $this->assertSame(0, Order::count());
    }
}
