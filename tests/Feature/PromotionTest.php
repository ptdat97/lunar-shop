<?php

namespace Tests\Feature;

use Illuminate\Validation\ValidationException;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Modules\Checkout\Services\CartService;
use Modules\Promotion\Database\Seeders\DemoCouponSeeder;
use Modules\Promotion\Services\PromotionService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Promotion: storefront coupon validation/preview (without applying) and the
 * human-readable discount summary surfaced to the storefront.
 */
class PromotionTest extends TestCase
{
    use CreatesStorefrontData;

    private function fixedCoupon(string $code, int $minor): Discount
    {
        $discount = Discount::create([
            'name' => 'Fixed off',
            'handle' => strtolower($code),
            'coupon' => $code,
            'type' => AmountOff::class,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'uses' => 0,
            'max_uses' => null,
            'priority' => 1,
            'stop' => false,
            'data' => [
                'fixed_value' => true,
                'fixed_values' => [Currency::getDefault()->code => $minor],
            ],
        ]);

        foreach (Channel::all() as $channel) {
            $discount->scheduleChannel($channel, now()->subDay());
        }
        foreach (CustomerGroup::all() as $group) {
            $discount->scheduleCustomerGroup($group, now()->subDay());
        }

        return $discount;
    }

    public function test_validate_endpoint_accepts_a_valid_coupon_without_applying_it(): void
    {
        $this->seed(DemoCouponSeeder::class);

        $this->postJson('/api/v1/cart/coupon/validate', ['code' => 'save10'])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('code', 'SAVE10')
            ->assertJsonPath('description', '10% off');

        // Validation must not have applied a coupon to the cart.
        $this->getJson('/api/v1/cart')->assertJsonPath('data.coupon_code', null);
    }

    public function test_validate_endpoint_rejects_an_unknown_coupon(): void
    {
        $this->postJson('/api/v1/cart/coupon/validate', ['code' => 'NOPE'])
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('message', 'This coupon code is invalid or has expired.');
    }

    public function test_describe_summarises_percentage_and_fixed_discounts(): void
    {
        $service = app(PromotionService::class);

        $this->seed(DemoCouponSeeder::class);
        $percent = $service->findByCoupon('SAVE10');
        $this->assertSame('10% off', $service->describe($percent));

        $fixed = $this->fixedCoupon('FIVER', 500);
        $this->assertStringContainsString('off', $service->describe($fixed));
    }

    public function test_available_coupons_include_a_description(): void
    {
        $this->seed(DemoCouponSeeder::class);

        $this->getJson('/api/v1/cart/coupons')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'SAVE10')
            ->assertJsonPath('data.0.description', '10% off');
    }

    /**
     * M1: submitting a valid-but-non-applying coupon must NOT wipe a coupon that
     * is already working on the cart — it should be restored, not nulled.
     */
    public function test_a_non_applying_coupon_does_not_wipe_the_working_one(): void
    {
        $this->seed(DemoCouponSeeder::class);
        $product = $this->createProduct(['price' => 5000]);

        $cartService = app(CartService::class);
        $cartService->add($product->skus->first()->id, 1);

        // Apply the working SAVE10 (10% of 50.00 = 5.00 off).
        $cart = $cartService->applyCoupon('SAVE10');
        $this->assertSame('SAVE10', $cart->coupon_code);
        $this->assertGreaterThan(0, $cart->discountTotal?->value);

        // A coupon that exists + is usable but yields NO discount on this cart:
        // a fixed coupon whose only limitation is a collection the product isn't in.
        $useless = $this->fixedCoupon('NOSPEND', 500);
        $collection = Collection::factory()->create();
        $useless->collections()->attach($collection->id, ['type' => 'limitation']);

        // Applying it is rejected...
        try {
            $cartService->applyCoupon('NOSPEND');
            $this->fail('a non-applying coupon should be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        // ...and the previously-working coupon is preserved, not nulled.
        $this->assertSame('SAVE10', app(CartService::class)->current()->coupon_code);
    }
}
