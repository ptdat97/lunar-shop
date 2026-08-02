<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * R3 — web↔API parity: GET /api/v1/products/{slug}?include=size_chart,related
 * lets a headless client fetch the same extras the web product page renders,
 * while the default shape stays unchanged (backwards compatible).
 */
class ProductIncludeTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_default_shape_has_no_extras(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $slug = $product->defaultUrl->slug;

        $this->getJson("/api/v1/products/{$slug}")
            ->assertOk()
            ->assertJsonMissingPath('data.size_chart')
            ->assertJsonMissingPath('data.related')
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'variants']]);
    }

    public function test_variant_payload_uses_sellable_stock_and_exposes_variant_key(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 2, 'variant_indexes' => [0, 1]]);
        $sku = $product->skus()->first();
        $sku->update(['committed' => 2]);
        $slug = $product->defaultUrl->slug;

        $this->getJson("/api/v1/products/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.variants.0.stock', 0)
            ->assertJsonPath('data.variants.0.on_hand', 2)
            ->assertJsonPath('data.variants.0.committed', 2)
            ->assertJsonPath('data.variants.0.variant_key', '0-1');
    }

    public function test_include_size_chart_attaches_chart(): void
    {
        $this->seedBaseData();
        $product = $this->attachSizeChart($this->createProduct());
        $slug = $product->defaultUrl->slug;

        $this->getJson("/api/v1/products/{$slug}?include=size_chart")
            ->assertOk()
            ->assertJsonPath('data.size_chart.has_chart', true)
            ->assertJsonStructure(['data' => ['size_chart' => ['name', 'measurements', 'rows']]]);
    }

    public function test_include_related_attaches_products(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $this->createProduct(['name' => 'Another Tee']); // candidate for related
        $slug = $product->defaultUrl->slug;

        // `related` key present (collection shape), even if empty — same
        // ProductResource shape, so the client renders it like any product card.
        $this->getJson("/api/v1/products/{$slug}?include=related")
            ->assertOk()
            ->assertJsonStructure(['data' => ['related']]);
    }
}
