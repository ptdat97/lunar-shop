<?php

namespace Tests\Feature;

use Modules\Promotion\Database\Seeders\DemoCouponSeeder;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class CartTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_add_to_cart(): void
    {
        $product = $this->createProduct(['price' => 2000]);
        $variantId = $product->variants->first()->id;

        $this->postJson('/api/v1/cart', ['variant_id' => $variantId, 'quantity' => 2])
            ->assertSuccessful()
            ->assertJsonPath('data.lines_count', 2)
            ->assertJsonPath('data.totals.sub_total', '$40.00');
    }

    public function test_add_requires_variant_id(): void
    {
        $this->postJson('/api/v1/cart', ['quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrorFor('variant_id');
    }

    public function test_update_and_remove_line(): void
    {
        $product = $this->createProduct(['price' => 1000]);
        $variantId = $product->variants->first()->id;

        $this->postJson('/api/v1/cart', ['variant_id' => $variantId, 'quantity' => 1]);
        $lineId = $this->getJson('/api/v1/cart')->json('data.lines.0.id');

        $this->patchJson("/api/v1/cart/lines/{$lineId}", ['quantity' => 3])
            ->assertSuccessful()
            ->assertJsonPath('data.lines_count', 3);

        $this->deleteJson("/api/v1/cart/lines/{$lineId}")
            ->assertSuccessful()
            ->assertJsonPath('data.lines_count', 0);
    }

    public function test_apply_and_remove_coupon(): void
    {
        $this->seed(DemoCouponSeeder::class);
        $product = $this->createProduct(['price' => 10000]); // $100
        $variantId = $product->variants->first()->id;
        $this->postJson('/api/v1/cart', ['variant_id' => $variantId, 'quantity' => 1]);

        // Coupon is accepted and recorded on the cart. (The discount *amount*
        // depends on Lunar's discount→channel/customer-group scoping pipeline,
        // which is exercised separately; here we assert the cart contract.)
        $this->postJson('/api/v1/cart/coupon', ['code' => 'SAVE10'])
            ->assertSuccessful()
            ->assertJsonPath('data.coupon_code', 'SAVE10');

        $this->deleteJson('/api/v1/cart/coupon')
            ->assertSuccessful()
            ->assertJsonPath('data.coupon_code', null);
    }

    public function test_invalid_coupon_is_rejected(): void
    {
        $product = $this->createProduct();
        $this->postJson('/api/v1/cart', ['variant_id' => $product->variants->first()->id, 'quantity' => 1]);

        $this->postJson('/api/v1/cart/coupon', ['code' => 'NOPE-INVALID'])
            ->assertStatus(422);
    }
}
