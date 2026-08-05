<?php

namespace Tests\Feature;

use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class SearchSizeTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_search_returns_contract_shape_with_facets_and_meta(): void
    {
        $this->createProduct(['name' => 'Linen Shirt', 'price' => 3000]);
        $this->createProduct(['name' => 'Denim Jacket', 'price' => 8000]);

        $this->getJson('/api/v1/search')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug']],
                'facets' => ['size', 'color'],
                'meta' => ['total', 'page', 'per_page', 'last_page'],
            ]);
    }

    public function test_search_filters_by_term(): void
    {
        $this->createProduct(['name' => 'Linen Shirt']);
        $this->createProduct(['name' => 'Denim Jacket']);

        $res = $this->getJson('/api/v1/search?q=Linen')->assertOk();
        $names = collect($res->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Linen Shirt'));
        $this->assertFalse($names->contains('Denim Jacket'));
    }

    public function test_size_filter_matches_values_on_the_size_axis_only(): void
    {
        $this->createProduct([
            'name' => 'Size S, colour M',
            'variables' => [
                ['name' => ['en' => 'Size'], 'values' => [['name' => ['en' => 'S']]]],
                ['name' => ['en' => 'Color'], 'values' => [['name' => ['en' => 'M']]]],
            ],
        ]);
        $matching = $this->createProduct([
            'name' => 'Size M',
            'variables' => [
                ['name' => ['en' => 'Size'], 'values' => [['name' => ['en' => 'M']]]],
                ['name' => ['en' => 'Color'], 'values' => [['name' => ['en' => 'Black']]]],
            ],
        ]);

        $result = app(SearchEngine::class)->search(
            new SearchQuery(filters: ['size' => ['M']]),
        );

        $this->assertSame([$matching->id], $result->items->pluck('id')->all());
    }

    public function test_suggest_endpoint(): void
    {
        $this->createProduct(['name' => 'Cashmere Sweater']);

        $this->getJson('/api/v1/search/suggest?q=Cash')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_size_chart_endpoint(): void
    {
        $product = $this->createProduct();
        $this->attachSizeChart($product);
        $slug = $product->defaultUrl->slug;

        $this->getJson("/api/v1/products/{$slug}/size-chart")
            ->assertOk()
            ->assertJsonPath('data.has_chart', true)
            ->assertJsonCount(3, 'data.rows');
    }

    public function test_recommend_size_returns_best_fit(): void
    {
        $product = $this->createProduct();
        $this->attachSizeChart($product);
        $slug = $product->defaultUrl->slug;

        // Body close to the M row (bust 88 / waist 70 / hip 94).
        $this->postJson("/api/v1/products/{$slug}/recommend-size", [
            'bust' => 88, 'waist' => 70, 'hip' => 94,
        ])
            ->assertOk()
            ->assertJsonPath('data.recommended.size', 'M')
            ->assertJsonStructure(['data' => ['recommended' => ['size', 'confidence'], 'alternatives', 'measured']]);
    }

    public function test_recommend_size_requires_a_measurement(): void
    {
        $product = $this->createProduct();
        $this->attachSizeChart($product);
        $slug = $product->defaultUrl->slug;

        $this->postJson("/api/v1/products/{$slug}/recommend-size", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('measurements');
    }
}
