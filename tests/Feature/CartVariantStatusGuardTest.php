<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * A variant the admin marked `status = disabled` must not enter the cart, no
 * matter how the request arrives. The storefront hides disabled variants, but
 * hiding a button is not a guard (coding standards §17.4) — CartService is the
 * enforcement point, so a direct API call must be refused too.
 *
 * Mutation check: delete `guardStatus()` (or its two call sites) in
 * CartService and these tests go red — that is what proves the guard runs.
 */
class CartVariantStatusGuardTest extends TestCase
{
    use CreatesStorefrontData;

    private function addLine(int $variantId, int $quantity = 1)
    {
        return $this->postJson('/api/v1/cart', ['sku_id' => $variantId, 'quantity' => $quantity]);
    }

    public function test_adding_a_disabled_variant_is_refused(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 100]);
        $variant = $product->skus->first();
        $variant->update(['status' => 'disabled']);

        // Plenty of stock — the only reason to reject is the disabled status.
        $this->addLine($variant->id)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('variant');

        $this->getJson('/api/v1/cart')->assertJsonPath('data.lines_count', 0);
    }

    public function test_a_published_variant_still_adds(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 100]);
        $variant = $product->skus->first();
        $variant->update(['status' => 'published']);

        $this->addLine($variant->id)
            ->assertSuccessful()
            ->assertJsonPath('data.lines_count', 1);
    }

    public function test_updating_a_line_of_a_disabled_variant_is_refused(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 100]);
        $variant = $product->skus->first();
        // Published so the line can be created, then disabled in the DB before a
        // fresh request updates it — the update request reloads the line, so its
        // purchasable reflects the disabled status.
        $variant->update(['status' => 'published']);

        $line = $this->addLine($variant->id)
            ->assertSuccessful()
            ->json('data.lines.0.id');

        $variant->update(['status' => 'disabled']);

        $this->patchJson("/api/v1/cart/lines/{$line}", ['quantity' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('variant');
    }
}
