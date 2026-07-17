<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The cart cannot hold more units than the variant can fulfil.
 *
 * `add()` used to check the *increment* against stock, so adding one unit five
 * times slipped a shopper past the last three. `updateLine()` had no guard at
 * all — `PATCH quantity=999` on a variant stocked at 3 was accepted, and only
 * failed at checkout (or, for a backorder variant, oversold silently).
 */
class CartStockGuardTest extends TestCase
{
    use CreatesStorefrontData;

    private function addLine(int $variantId, int $quantity = 1)
    {
        return $this->postJson('/api/v1/cart', ['sku_id' => $variantId, 'quantity' => $quantity]);
    }

    public function test_a_single_add_beyond_stock_is_refused(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 3]);

        $this->addLine($product->skus->first()->id, 4)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('quantity');
    }

    public function test_repeated_adds_cannot_creep_past_stock(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 3]);
        $variantId = $product->skus->first()->id;

        foreach (range(1, 3) as $i) {
            $this->addLine($variantId)->assertSuccessful();
        }

        // The fourth unit does not exist.
        $this->addLine($variantId)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('quantity');

        $this->getJson('/api/v1/cart')->assertJsonPath('data.lines_count', 3);
    }

    public function test_updating_a_line_beyond_stock_is_refused(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 3]);

        $line = $this->addLine($product->skus->first()->id)
            ->assertSuccessful()
            ->json('data.lines.0.id');

        $this->patchJson("/api/v1/cart/lines/{$line}", ['quantity' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('quantity');

        $this->getJson('/api/v1/cart')->assertJsonPath('data.lines_count', 1);
    }

    public function test_updating_a_line_up_to_stock_is_allowed(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 3]);

        $line = $this->addLine($product->skus->first()->id)
            ->assertSuccessful()
            ->json('data.lines.0.id');

        $this->patchJson("/api/v1/cart/lines/{$line}", ['quantity' => 3])
            ->assertSuccessful()
            ->assertJsonPath('data.lines_count', 3);
    }

    public function test_a_sku_cannot_exceed_its_stock(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 1]);
        $variant = $product->skus->first();

        // No backorder mode: adding beyond stock is refused outright, and a
        // later quantity bump past stock is refused too.
        $this->addLine($variant->id, 50)->assertStatus(422);

        $this->addLine($variant->id, 1)->assertSuccessful();
        $line = $this->getJson('/api/v1/cart')->json('data.lines.0.id');
        $this->patchJson("/api/v1/cart/lines/{$line}", ['quantity' => 100])->assertStatus(422);
    }

    public function test_reducing_a_line_is_always_allowed(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 3]);

        $line = $this->addLine($product->skus->first()->id, 3)
            ->assertSuccessful()
            ->json('data.lines.0.id');

        // Stock later drops below what is already in the cart; the shopper must
        // still be able to take some out.
        $product->skus->first()->update(['quantity' => 1]);

        $this->patchJson("/api/v1/cart/lines/{$line}", ['quantity' => 1])->assertSuccessful();
    }

    public function test_raising_a_line_past_freshly_dropped_stock_is_refused(): void
    {
        // M2: the updateLine guard must read stock FRESH, not off the cached
        // cart line. A concurrent sale drops stock after the line was loaded;
        // bumping the quantity past the real level must be rejected.
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 10]);
        $sku = $product->skus->first();

        $line = $this->addLine($sku->id, 2)->assertSuccessful()->json('data.lines.0.id');

        // Someone else buys almost all of it: only 3 remain now.
        $sku->update(['quantity' => 3]);

        $this->patchJson("/api/v1/cart/lines/{$line}", ['quantity' => 8])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('quantity');

        // But raising up to the real available level is allowed.
        $this->patchJson("/api/v1/cart/lines/{$line}", ['quantity' => 3])->assertSuccessful();
    }
}
