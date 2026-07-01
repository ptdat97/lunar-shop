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
