<?php

namespace Tests\Feature;

use Acme\Preorder\PreorderService;
use Modules\Hook\Plugin\PluginManager;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Second reference plugin (acme/preorder): buy an out-of-stock product when it's
 * flagged pre-order, plus a `preorder` payload badge — loaded purely through the
 * SDK + hooks, ZERO core edits. It runs its purchasable filter AFTER Inventory's
 * oversell veto and overrides it, proving the priority-ordered hook chain works
 * across plugins.
 */
class PreorderPluginTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        config(['plugins.enabled' => ['acme/preorder']]);

        $manager = new PluginManager($this->app);
        $manager->install('acme/preorder');
        $manager->load();
        $manager->boot();
    }

    public function test_out_of_stock_product_cannot_be_bought_without_preorder(): void
    {
        $product = $this->createProduct(['stock' => 0]);
        $product->variants->first()->update(['purchasable' => 'in_stock']);

        // Inventory vetoes the oversell; no pre-order flag → still blocked.
        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrorFor('quantity');
    }

    public function test_preorder_flag_allows_buying_out_of_stock_and_adds_badge(): void
    {
        $product = $this->createProduct(['slug' => 'preorder-tee', 'stock' => 0]);
        $product->variants->first()->update(['purchasable' => 'in_stock']);

        app(PreorderService::class)->enable($product->id, '2026-09-01');

        // Now the same out-of-stock variant can be added (pre-order override).
        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        // And the product payload carries the pre-order badge via the filter.
        $this->getJson('/api/v1/products/preorder-tee')
            ->assertOk()
            ->assertJsonPath('data.preorder.enabled', true)
            ->assertJsonPath('data.preorder.expected_at', '2026-09-01');
    }

    public function test_in_stock_purchase_is_unaffected_by_the_plugin(): void
    {
        $product = $this->createProduct(['slug' => 'normal-tee', 'stock' => 5]);
        $product->variants->first()->update(['purchasable' => 'in_stock']);

        $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => 2,
        ])->assertSuccessful();

        // No pre-order flag → no badge leaks into the payload.
        $this->getJson('/api/v1/products/normal-tee')
            ->assertOk()
            ->assertJsonMissingPath('data.preorder');
    }
}
