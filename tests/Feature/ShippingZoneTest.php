<?php

namespace Tests\Feature;

use Lunar\Models\Cart;
use Lunar\Models\Country;
use Modules\Checkout\Services\CartService;
use Modules\Shipping\Models\ShippingZone;
use Modules\Shipping\Services\ShippingZoneResolver;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Shipping: DB-backed zones drive the rate, with most-specific-wins matching,
 * a free-shipping threshold, and a fallback to the static config rate.
 */
class ShippingZoneTest extends TestCase
{
    use CreatesStorefrontData;

    /** Build a cart with a shipping address in the given country + state. */
    private function cartForAddress(string $iso2, ?string $state, int $price = 5000): Cart
    {
        $country = Country::where('iso2', $iso2)->firstOr(function () use ($iso2) {
            return Country::create([
                'name' => $iso2, 'iso3' => $iso2.'X', 'iso2' => $iso2,
                'phonecode' => '0', 'capital' => '', 'currency' => 'USD', 'native' => $iso2,
                'emoji' => '', 'emoji_u' => '',
            ]);
        });

        $product = $this->createProduct(['price' => $price, 'stock' => 50]);
        $this->postJson('/api/v1/cart', ['sku_id' => $product->skus->first()->id, 'quantity' => 1]);
        $this->postJson('/api/v1/checkout/addresses', [
            'shipping' => $this->shippingPayload(['country_id' => $country->id, 'state' => $state]),
        ])->assertSuccessful();

        return app(CartService::class)->current();
    }

    public function test_falls_back_to_config_rate_when_no_zone_matches(): void
    {
        config(['shipping.standard_rate' => 4200, 'shipping.free_threshold' => 0]);

        $cart = $this->cartForAddress('VN', 'Thành phố Hồ Chí Minh');

        $this->assertSame(4200, app(ShippingZoneResolver::class)->rateForCart($cart));
    }

    public function test_country_wide_zone_rate_is_used(): void
    {
        ShippingZone::create([
            'name' => 'Vietnam', 'country_code' => 'VN', 'states' => null,
            'rate' => 2500, 'free_threshold' => 0, 'enabled' => true, 'priority' => 0,
        ]);

        $cart = $this->cartForAddress('VN', 'Thành phố Hồ Chí Minh');

        $this->assertSame(2500, app(ShippingZoneResolver::class)->rateForCart($cart));
    }

    public function test_state_scoped_zone_beats_country_wide_zone(): void
    {
        ShippingZone::create([
            'name' => 'Vietnam', 'country_code' => 'VN', 'states' => null,
            'rate' => 2500, 'free_threshold' => 0, 'enabled' => true, 'priority' => 0,
        ]);
        ShippingZone::create([
            'name' => 'HCMC', 'country_code' => 'VN', 'states' => ['Thành phố Hồ Chí Minh'],
            'rate' => 1500, 'free_threshold' => 0, 'enabled' => true, 'priority' => 0,
        ]);

        $cart = $this->cartForAddress('VN', 'Thành phố Hồ Chí Minh');

        $this->assertSame(1500, app(ShippingZoneResolver::class)->rateForCart($cart));
    }

    public function test_free_threshold_zeros_the_rate(): void
    {
        ShippingZone::create([
            'name' => 'Vietnam', 'country_code' => 'VN', 'states' => null,
            'rate' => 2500, 'free_threshold' => 4000, 'enabled' => true, 'priority' => 0,
        ]);

        // Sub-total 5000 >= 4000 → free.
        $cart = $this->cartForAddress('VN', 'Thành phố Hồ Chí Minh', price: 5000);

        $this->assertSame(0, app(ShippingZoneResolver::class)->rateForCart($cart));
    }

    public function test_disabled_zone_is_ignored(): void
    {
        config(['shipping.standard_rate' => 3000]);

        ShippingZone::create([
            'name' => 'Vietnam', 'country_code' => 'VN', 'states' => null,
            'rate' => 999, 'free_threshold' => 0, 'enabled' => false, 'priority' => 0,
        ]);

        $cart = $this->cartForAddress('VN', 'Thành phố Hồ Chí Minh');

        $this->assertSame(3000, app(ShippingZoneResolver::class)->rateForCart($cart));
    }

    public function test_storefront_shipping_option_reflects_the_zone_rate(): void
    {
        ShippingZone::create([
            'name' => 'Vietnam', 'country_code' => 'VN', 'states' => null,
            'rate' => 1234, 'free_threshold' => 0, 'enabled' => true, 'priority' => 0,
        ]);

        $this->cartForAddress('VN', 'Thành phố Hồ Chí Minh');

        // The option's formatted price (minor units 1234 → 12.34) reflects the
        // zone rate flowing through the modifier.
        $this->getJson('/api/v1/checkout/shipping-options')
            ->assertOk()
            ->assertJsonPath('data.0.identifier', 'standard')
            ->assertJsonFragment(['identifier' => 'standard'])
            ->assertSee('12.34');
    }
}
