<?php

namespace Tests\Feature;

use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
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
        // Two axes, so the positional key the picker jumps by has two parts.
        // The variant takes the FIRST colour and the SECOND size, i.e. "0-1".
        $product = $this->createProduct([
            'stock' => 2,
            'options' => ['Color' => 'Black', 'Size' => 'M'],
        ]);

        // A second variant on size S. The axis only lists values some variant
        // uses — offering a swatch that resolves to nothing is worse than not
        // offering it — so without this, M would be the only size and index 0.
        $small = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'INC-S-'.uniqid(),
            'tax_class_id' => TaxClass::getDefault()?->id,
            'enabled' => true,
        ]);
        $sizeS = $this->optionValue($product, 'Size', 'S');
        $small->values()->syncWithoutDetaching([
            $this->optionValue($product, 'Color', 'Black')->id,
            $sizeS->id,
        ]);

        // Sizes run S, M — the axis is ordered by the value's own position, so
        // the M variant sits at index 1 and its key reads "0-1".
        $sizeS->forceFill(['position' => 1])->save();
        $this->optionValue($product, 'Size', 'M')->forceFill(['position' => 2])->save();

        $sku = $product->variants()->first();
        $sku->forceFill(['stock_committed' => 2, 'stock_available' => (int) $sku->stock_on_hand - 2])->save();
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
