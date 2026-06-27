<?php

namespace Tests\Feature;

use Modules\Platform\Facades\Hook;
use Modules\Platform\Support\Hooks;
use Modules\Pricing\Services\PricingService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * D2 — the new commerce FILTER hooks (cart.totals / checkout.validate /
 * price.display) let a plugin adjust totals, veto checkout, or rewrite the
 * displayed price without editing the core services. Additive: with no listener
 * the behaviour is unchanged (covered by the rest of the suite).
 */
class CommerceFiltersTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_cart_totals_filter_can_add_a_total_line(): void
    {
        Hook::addFilter(Hooks::CART_TOTALS, function (array $totals, $cart): array {
            $totals['gift_wrap'] = '$5.00';

            return $totals;
        });

        $product = $this->createProduct(['price' => 5000, 'stock' => 5]);
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);

        $this->getJson('/api/v1/cart')
            ->assertSuccessful()
            ->assertJsonPath('data.totals.gift_wrap', '$5.00');
    }

    public function test_price_display_filter_can_rewrite_the_shown_price(): void
    {
        Hook::addFilter(Hooks::PRICE_DISPLAY, fn (?string $price, $product) => 'from '.$price);

        $product = $this->createProduct(['price' => 6000]);

        $this->assertStringStartsWith('from ', app(PricingService::class)->displayPrice($product));
    }

    public function test_checkout_validate_filter_can_veto_an_order(): void
    {
        Hook::addFilter(Hooks::CHECKOUT_VALIDATE, function (array $errors, $cart): array {
            $errors['fraud'] = 'Order blocked by risk check.';

            return $errors;
        });

        $product = $this->createProduct(['price' => 5000, 'stock' => 10]);
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('fraud');
    }

    public function test_checkout_proceeds_when_validate_returns_no_errors(): void
    {
        // A listener that adds nothing must not block checkout.
        Hook::addFilter(Hooks::CHECKOUT_VALIDATE, fn (array $errors, $cart) => $errors);

        $product = $this->createProduct(['price' => 5000, 'stock' => 10]);
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();
    }
}
