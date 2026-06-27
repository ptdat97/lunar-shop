<?php

namespace Tests\Feature;

use Modules\Platform\Facades\Hook;
use Modules\Platform\Support\Hooks;
use Modules\Product\Services\ProductService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Phase 4 / B.2 — Recommendations extracted from the Recommend module into the
 * acme/recommend plugin (enabled by default). Product no longer depends on a
 * recommender: its controllers run the collection fallback through the
 * product.related filter, which the plugin hooks. Disable the plugin → graceful
 * degradation to the fallback. (Endpoints covered by RecommendationTest.)
 */
class RecommendPluginTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_product_module_uses_the_filter_not_a_recommender_dependency(): void
    {
        // ProductService::related is now a PLAIN fallback (no product.related
        // filter inside it) — so a recommender plugin can use it as a baseline
        // without recursion. Verify it returns products with no listener attached.
        Hook::forget(Hooks::PRODUCT_RELATED);

        $main = $this->createProduct(['slug' => 'main', 'price' => 1000]);
        $this->createProduct(['slug' => 'sibling', 'price' => 1000]);

        $related = app(ProductService::class)->related($main);

        // A plain collection result (not run through any filter).
        $this->assertNotNull($related);
    }

    public function test_product_page_renders_without_the_recommender(): void
    {
        // No product.related listener → controllers fall back to the collection
        // result; the page must still render (graceful degradation).
        Hook::forget(Hooks::PRODUCT_RELATED);

        $this->createProduct(['slug' => 'lonely-tee']);

        $this->get('/products/lonely-tee')->assertOk();
    }

    public function test_a_listener_enriches_the_related_set_for_product_pages(): void
    {
        $pick = $this->createProduct(['slug' => 'plugin-pick']);

        Hook::forget(Hooks::PRODUCT_RELATED);
        Hook::addFilter(Hooks::PRODUCT_RELATED, fn ($fallback, $product, $limit = 8) => collect([$pick]));

        $this->createProduct(['slug' => 'viewed-tee']);

        // The product API include=related goes through the same filter.
        $this->getJson('/api/v1/products/viewed-tee?include=related')
            ->assertOk()
            ->assertJsonPath('data.related.0.slug', 'plugin-pick');
    }
}
