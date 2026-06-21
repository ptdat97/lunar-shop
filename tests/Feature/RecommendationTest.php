<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Recommendations API: product page + mini-cart. Both return the standard
 * ProductResource shape so the storefront card renderer reuses them.
 */
class RecommendationTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_product_recommendations_return_curated_association_first(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['name' => 'Source Tee']);
        $curated = $this->createProduct(['name' => 'Curated Match']);

        // Hand-picked cross-sell — should surface in recommendations.
        $product->associate($curated, 'cross-sell');

        $slug = $product->defaultUrl->slug;

        $this->getJson("/api/v1/products/{$slug}/recommendations")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'variants']]])
            ->assertJsonFragment(['id' => $curated->id]);
    }

    public function test_product_recommendations_404_for_unknown_slug(): void
    {
        $this->seedBaseData();

        $this->getJson('/api/v1/products/nope/recommendations')
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_cart_recommendations_exclude_products_in_cart(): void
    {
        $this->seedBaseData();

        $inCart = $this->createProduct(['name' => 'In Cart']);
        $curated = $this->createProduct(['name' => 'Suggested']);
        $inCart->associate($curated, 'cross-sell');

        // Put the source product in the cart.
        $this->postJson('/api/v1/cart', [
            'variant_id' => $inCart->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $response = $this->getJson('/api/v1/cart/recommendations')
            ->assertOk()
            ->assertJsonStructure(['data' => []]);

        // The product already in the cart must not be recommended back.
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($inCart->id), 'cart product should be excluded');
    }
}
