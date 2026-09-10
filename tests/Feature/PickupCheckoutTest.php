<?php

namespace Tests\Feature;

use Lunar\Core\Models\Country;
use Lunar\Core\Models\Order;
use Modules\Checkout\Services\CartService;
use Modules\Checkout\Services\CheckoutService;
use Modules\Core\Support\Settings;
use Modules\Shipping\Services\PickupLocation;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Collect-at-the-counter (roadmap §13).
 *
 * The one delivery capability that needs no carrier contract, so it ships ahead
 * of the rest of P0.5. Lunar writes a shipping address row on every order, so
 * pickup does not skip the address — it fills the destination with the shop's
 * own while keeping the customer's name and phone.
 *
 * Mutation check: drop the `isAvailable()` guard in PickupShippingModifier and
 * the "hidden until configured" tests go red.
 */
class PickupCheckoutTest extends TestCase
{
    use CreatesStorefrontData;

    private function configurePickup(array $overrides = []): void
    {
        app(Settings::class)->put('shipping', array_merge([
            'standard_rate' => 3000,
            'free_threshold' => 0,
            'pickup_enabled' => true,
            'pickup_name' => 'Cửa hàng Quận 1',
            'pickup_line_one' => '12 Nguyễn Huệ',
            'pickup_city' => 'Phường Bến Nghé',
            'pickup_state' => 'TP. Hồ Chí Minh',
            'pickup_hours' => '9:00 – 21:00 hằng ngày',
            'pickup_instructions' => 'Mang theo mã đơn hàng.',
        ], $overrides));
    }

    private function addToCart(): void
    {
        $product = $this->createProduct(['stock' => 10]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();
    }

    /** @return array<string, mixed> */
    private function contact(): array
    {
        return [
            'first_name' => 'Dat',
            'last_name' => 'Pham',
            'country_id' => Country::first()->id,
            'contact_phone' => '0900000000',
            'contact_email' => 'dat@example.test',
        ];
    }

    public function test_the_option_is_hidden_until_the_shop_configures_a_counter(): void
    {
        $this->seedBaseData();
        $this->addToCart();

        $identifiers = collect($this->getJson('/api/v1/checkout/shipping-options')->json('data'))
            ->pluck('identifier');

        $this->assertContains('standard', $identifiers);
        $this->assertNotContains(PickupLocation::IDENTIFIER, $identifiers);
    }

    /** Switched on but with no address is still not offerable — worse than absent. */
    public function test_the_option_stays_hidden_when_enabled_without_an_address(): void
    {
        $this->seedBaseData();
        $this->configurePickup(['pickup_line_one' => '', 'pickup_city' => '']);
        $this->addToCart();

        $identifiers = collect($this->getJson('/api/v1/checkout/shipping-options')->json('data'))
            ->pluck('identifier');

        $this->assertNotContains(PickupLocation::IDENTIFIER, $identifiers);
    }

    public function test_a_configured_counter_is_offered_at_zero_cost(): void
    {
        $this->seedBaseData();
        $this->configurePickup();
        $this->addToCart();

        $option = collect($this->getJson('/api/v1/checkout/shipping-options')->json('data'))
            ->firstWhere('identifier', PickupLocation::IDENTIFIER);

        $this->assertNotNull($option, 'Pickup was configured but not offered.');
        $this->assertSame(0, (int) data_get($option, 'price.value', data_get($option, 'price')));
    }

    public function test_the_checkout_context_carries_the_counter_details(): void
    {
        $this->seedBaseData();
        $this->configurePickup();

        $pickup = app(CheckoutService::class)->paymentContext()['pickup'];

        $this->assertSame('Cửa hàng Quận 1', $pickup['name']);
        $this->assertSame('12 Nguyễn Huệ, Phường Bến Nghé, TP. Hồ Chí Minh', $pickup['address']);
        $this->assertSame('9:00 – 21:00 hằng ngày', $pickup['hours']);
    }

    public function test_context_pickup_is_null_when_not_offered(): void
    {
        $this->seedBaseData();

        $this->assertNull(
            app(CheckoutService::class)->paymentContext()['pickup']
        );
    }

    /**
     * The heart of it: contact details only, and the order still gets a real
     * destination — the shop's — plus the customer's phone.
     */
    public function test_collecting_needs_no_delivery_address(): void
    {
        $this->seedBaseData();
        $this->configurePickup();
        $this->addToCart();

        $this->postJson('/api/v1/checkout/pickup', $this->contact())
            ->assertSuccessful();

        $order = $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertSuccessful()
            ->json('data');

        $this->assertSame(0, (int) preg_replace('/\D/', '', (string) $order['shipping_total']));

        $address = $order['shipping_address'];
        $this->assertStringContainsString('12 Nguyễn Huệ', $address['line_one']);
        $this->assertStringContainsString('Dat', $address['name']);
    }

    /**
     * A pickup order must reach the panel as something to hand over, not
     * something to post.
     *
     * Lunar decides that from one flag: `ShippingOption::$collect`, which
     * CreateOrder stamps onto the shipping line's meta and the Collection
     * fulfilment method reads to claim the order's lines. Our pickup option
     * left it at its default `false`, so the Shipping method claimed the order
     * instead and staff were shown ship / add-tracking actions for a customer
     * walking into the shop. Nothing on the storefront looked wrong — the
     * symptom lived entirely in the admin.
     */
    public function test_a_pickup_order_is_fulfilled_by_collection_not_shipping(): void
    {
        $this->seedBaseData();
        $this->configurePickup();
        $this->addToCart();

        $this->postJson('/api/v1/checkout/pickup', $this->contact())->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $order = Order::latest('id')->firstOrFail();

        $shippingLine = $order->lines()->where('type', 'shipping')->first();
        $this->assertTrue(
            (bool) ($shippingLine?->meta['collect'] ?? false),
            'Dòng phí ship của đơn nhận tại cửa hàng không mang cờ `collect`.',
        );

        $methods = $order->fulfilments()->pluck('method')->all();

        $this->assertContains('collection', $methods, 'Đơn nhận tại cửa hàng không có fulfilment `collection`.');
        $this->assertNotContains('shipping', $methods, 'Đơn nhận tại cửa hàng vẫn bị giao cho phương thức `shipping`.');
    }

    /** The address and the shipping option must never disagree. */
    public function test_collecting_selects_the_pickup_shipping_option(): void
    {
        $this->seedBaseData();
        $this->configurePickup();
        $this->addToCart();

        $this->postJson('/api/v1/checkout/pickup', $this->contact())->assertSuccessful();

        $cart = app(CartService::class)->current();

        $this->assertSame(
            PickupLocation::IDENTIFIER,
            $cart->shippingAddress?->shipping_option,
        );
    }

    /** A phone is mandatory here: with no address it is the only way to reach them. */
    public function test_a_phone_number_is_required(): void
    {
        $this->seedBaseData();
        $this->configurePickup();
        $this->addToCart();

        $this->postJson('/api/v1/checkout/pickup', array_merge($this->contact(), ['contact_phone' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('contact_phone');
    }

    /** Posting to the endpoint while pickup is off must not smuggle an order through. */
    public function test_collecting_is_refused_when_the_shop_does_not_offer_it(): void
    {
        $this->seedBaseData();
        $this->addToCart();

        $this->postJson('/api/v1/checkout/pickup', $this->contact())
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('shipping');
    }

    /**
     * The SSR form is the path real shoppers take. Posting it with the address
     * fields blank must work when collecting — the storefront hides them, so
     * the browser sends nothing.
     */
    public function test_the_ssr_form_places_a_collection_order_without_address_fields(): void
    {
        $this->seedBaseData();
        $this->configurePickup();
        $this->addToCart();

        $this->post('/checkout', [
            'first_name' => 'Dat',
            'last_name' => 'Pham',
            'line_one' => '',
            'state' => '',
            'city' => '',
            'country_id' => Country::first()->id,
            'contact_email' => 'dat@example.test',
            'contact_phone' => '0900000000',
            'shipping_option' => PickupLocation::IDENTIFIER,
            'payment_type' => 'cod',
        ])->assertRedirect();

        $order = Order::latest('id')->first();

        $this->assertNotNull($order);
        $this->assertSame(0, (int) $order->shipping_total);
        $this->assertSame('12 Nguyễn Huệ', $order->shippingAddress->line_one);
    }

    /**
     * And the guard behind it: the same blank-address post at a shop that has
     * collection switched OFF must be rejected, not quietly accepted with an
     * empty destination. Hiding a field is not a guard (standards §17.4).
     */
    public function test_the_ssr_form_still_demands_an_address_when_collection_is_off(): void
    {
        $this->seedBaseData();
        $this->addToCart();

        $this->post('/checkout', [
            'first_name' => 'Dat',
            'last_name' => 'Pham',
            'line_one' => '',
            'state' => '',
            'city' => '',
            'country_id' => Country::first()->id,
            'contact_email' => 'dat@example.test',
            'contact_phone' => '0900000000',
            'shipping_option' => PickupLocation::IDENTIFIER,
            'payment_type' => 'cod',
        ])->assertSessionHasErrors(['line_one', 'state', 'city']);
    }

    /** The checkout page renders the counter details server-side. */
    public function test_the_checkout_page_shows_the_counter(): void
    {
        $this->seedBaseData();
        $this->configurePickup();
        $this->addToCart();

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('Cửa hàng Quận 1', false)
            ->assertSee('12 Nguyễn Huệ, Phường Bến Nghé', false);
    }
}
