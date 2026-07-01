<?php

namespace Tests\Feature;

use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Extended facets (price + brand) on the search engine, and the ordered
 * `?slugs=` product lookup that powers "recently viewed".
 */
class FacetAndRecentlyViewedTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_search_facets_include_price_bounds(): void
    {
        $this->seedBaseData();
        $this->createProduct(['name' => 'Cheap Tee', 'price' => 1000]);  // $10
        $this->createProduct(['name' => 'Pricey Coat', 'price' => 9900]); // $99

        $result = app(SearchEngine::class)->search(new SearchQuery(perPage: 10));

        $this->assertArrayHasKey('price', $result->facets);
        $this->assertArrayHasKey('brand', $result->facets);

        $price = $result->facets['price'];
        $this->assertNotNull($price);
        $this->assertSame(10.0, $price['min']);
        $this->assertSame(99.0, $price['max']);
    }

    public function test_price_filter_narrows_results(): void
    {
        $this->seedBaseData();
        $this->createProduct(['name' => 'Cheap Tee', 'price' => 1000]);  // $10
        $this->createProduct(['name' => 'Pricey Coat', 'price' => 9900]); // $99

        $engine = app(SearchEngine::class);

        $all = $engine->search(new SearchQuery(perPage: 50))->total;
        $expensive = $engine->search(new SearchQuery(perPage: 50, filters: ['price' => ['min' => 50]]))->total;

        $this->assertSame($all - 1, $expensive, 'min=50 should exclude the $10 tee');
    }

    public function test_material_facet_and_filter(): void
    {
        $this->seedBaseData();
        $cotton = $this->createProduct(['name' => 'Cotton Tee']);
        $denim = $this->createProduct(['name' => 'Denim Jacket']);
        \DB::table('product_materials')->insert([
            ['product_id' => $cotton->id, 'material' => 'Cotton'],
            ['product_id' => $denim->id, 'material' => 'Denim'],
        ]);

        $engine = app(SearchEngine::class);
        $result = $engine->search(new SearchQuery(perPage: 50));

        $this->assertArrayHasKey('material', $result->facets);
        $materials = collect($result->facets['material'])->pluck('value')->all();
        $this->assertContains('Cotton', $materials);
        $this->assertContains('Denim', $materials);

        // Filtering by material keeps only matching products.
        $filtered = $engine->search(new SearchQuery(perPage: 50, filters: ['material' => ['Cotton']]));
        $this->assertSame(1, $filtered->total);
        $this->assertSame($cotton->id, $filtered->items->first()->id);
    }

    public function test_availability_facet_and_in_stock_filter(): void
    {
        $this->seedBaseData();
        $inStock = $this->createProduct(['name' => 'In Stock', 'stock' => 5]);
        $this->createProduct(['name' => 'Sold Out', 'stock' => 0]);

        $engine = app(SearchEngine::class);
        $result = $engine->search(new SearchQuery(perPage: 50));

        // Availability facet reports the in-stock count as a single bucket.
        $availability = collect($result->facets['availability']);
        $this->assertSame('in_stock', $availability->first()['value'] ?? null);

        // Filtering by availability=in_stock excludes the sold-out product.
        $filtered = $engine->search(new SearchQuery(perPage: 50, filters: ['availability' => ['in_stock']]));
        $ids = $filtered->items->pluck('id')->all();
        $this->assertContains($inStock->id, $ids);
        $this->assertSame($filtered->total, $filtered->items->count());
        $this->assertTrue($filtered->items->every(fn ($p) => $p->variants->sum('stock') > 0));
    }

    public function test_products_endpoint_returns_slugs_in_order(): void
    {
        $this->seedBaseData();
        $a = $this->createProduct(['name' => 'Alpha', 'slug' => 'alpha-x']);
        $b = $this->createProduct(['name' => 'Beta', 'slug' => 'beta-x']);

        // Request b first, then a — the response must preserve that order.
        $response = $this->getJson('/api/v1/products?slugs=beta-x,alpha-x')->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertSame(['beta-x', 'alpha-x'], $slugs);
        $this->assertCount(2, $slugs);
    }
}
