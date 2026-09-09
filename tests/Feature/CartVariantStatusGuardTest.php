<?php

namespace Tests\Feature;

use Lunar\Core\Exceptions\Carts\CartException;
use Lunar\Core\Facades\CartSession;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * A variant that can no longer be bought must not enter the cart, no matter how
 * the request arrives. The storefront hides such variants, but hiding a button
 * is not a guard (coding standards §17.4) — CartService is the enforcement
 * point, so a direct API call must be refused too.
 *
 * "Can no longer be bought" widened with Lunar 1.5: guardStatus now delegates to
 * ProductSku::isPurchasable(), so a retired PARENT PRODUCT counts as well. The
 * old inline check only read the SKU's own `status`, which let a variant of an
 * unpublished or soft-deleted product straight into the cart.
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

    public function test_adding_a_variant_of_an_unpublished_product_is_refused(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 100]);
        $variant = $product->skus->first();

        // The SKU itself stays published — the only reason to reject is that its
        // product went back to draft.
        $product->update(['status' => 'draft']);

        $this->addLine($variant->id)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('variant');

        $this->getJson('/api/v1/cart')->assertJsonPath('data.lines_count', 0);
    }

    /**
     * Lunar 2.0 dropped SoftDeletes from Product: retiring one is now
     * `status = 'archived'`, and `delete()` really deletes (taking its SKUs
     * with it, so there is no variant left to add). Archiving is therefore the
     * case worth guarding — the SKU still exists and could still be posted.
     */
    public function test_adding_a_variant_of_an_archived_product_is_refused(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 100]);
        $variant = $product->skus->first();

        $product->update(['status' => 'archived']);

        $this->addLine($variant->id)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('variant');
    }

    /**
     * The SKU itself still soft-deletes, and a deleted one is not buyable.
     *
     * 404 rather than 422: `CartService::add()` resolves the SKU with
     * `findOrFail()` before any guard runs, so a deleted one never reaches the
     * availability check. That is the right answer — the resource is gone, not
     * merely unavailable — and it is what the endpoint has always done.
     */
    public function test_adding_a_soft_deleted_variant_is_refused(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 100]);
        $variant = $product->skus->first();

        $variant->delete();

        $this->addLine($variant->id)->assertNotFound();

        $this->getJson('/api/v1/cart')->assertJsonPath('data.lines_count', 0);
    }

    public function test_updating_a_line_after_its_product_is_unpublished_is_refused(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 100]);
        $variant = $product->skus->first();

        $line = $this->addLine($variant->id)
            ->assertSuccessful()
            ->json('data.lines.0.id');

        $product->update(['status' => 'draft']);

        $this->patchJson("/api/v1/cart/lines/{$line}", ['quantity' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('variant');
    }

    /**
     * CartService gives the shopper a friendly 422, but it is not the only way
     * into a cart — admin draft orders and any future code calling $cart->add()
     * bypass it entirely. Lunar 1.5's CartLineAvailability validator, wired up in
     * config/lunar/cart.php, is the last-line defence there; it reads the very
     * same isPurchasable().
     *
     * Mutation check: remove CartLineAvailability from the `add_to_cart`
     * validators and this test goes red.
     */
    public function test_lunar_refuses_the_line_when_cart_service_is_bypassed(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 100]);
        $variant = $product->skus->first();
        $product->update(['status' => 'draft']);

        $cart = CartSession::current();

        $this->expectException(CartException::class);

        $cart->add($variant->fresh(), 1);
    }
}
