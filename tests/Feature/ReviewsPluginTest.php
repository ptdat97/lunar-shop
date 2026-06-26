<?php

namespace Tests\Feature;

use Modules\Platform\Plugin\PluginManager;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * E3 — the reference plugin (acme/reviews) proves the SDK is sufficient: a
 * self-contained feature (table + routes + payload enrichment) loaded purely
 * through the Plugin contract + hooks, with ZERO core edits.
 *
 * The plugin lives under the real plugins/ dir, so we use the default config
 * paths and just enable + install it.
 */
class ReviewsPluginTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        config(['plugins.enabled' => ['acme/reviews']]);

        // Install (runs the plugin's own migration) + load + boot it, mirroring
        // what ModulesServiceProvider does at app boot once it's enabled.
        $manager = new PluginManager($this->app);
        $manager->install('acme/reviews');
        $manager->load();
        $manager->boot();
    }

    public function test_plugin_routes_and_payload_enrichment_work_end_to_end(): void
    {
        $product = $this->createProduct(['slug' => 'reviewed-tee']);

        // 1. The plugin's own endpoint exists (its routes loaded).
        $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'author' => 'Mai',
            'rating' => 5,
            'body' => 'Lovely fit',
        ])->assertCreated()->assertJsonPath('data.count', 1);

        $this->postJson("/api/v1/products/{$product->id}/reviews", [
            'author' => 'Lan',
            'rating' => 3,
        ])->assertCreated();

        // 2. The list endpoint returns reviews + summary.
        $this->getJson("/api/v1/products/{$product->id}/reviews")
            ->assertOk()
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.average', 4);

        // 3. The product API payload carries the `reviews` block — contributed
        //    purely through the product.resource filter, no ProductResource edit.
        $this->getJson('/api/v1/products/reviewed-tee')
            ->assertOk()
            ->assertJsonPath('data.reviews.count', 2)
            ->assertJsonPath('data.reviews.average', 4);
    }
}
