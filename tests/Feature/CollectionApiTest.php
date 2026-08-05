<?php

namespace Tests\Feature;

use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Language;
use Lunar\Models\Url;
use Modules\Catalog\Models\ProductSku;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class CollectionApiTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_collection_api_uses_published_skus_and_sku_prices_for_sorting(): void
    {
        $expensive = $this->createProduct(['name' => 'Expensive', 'price' => 9000]);
        $cheap = $this->createProduct(['name' => 'Cheap', 'price' => 1000]);
        $collection = $this->createCollection($expensive, $cheap);

        // An admin-disabled SKU must never be exposed in a public listing.
        ProductSku::create([
            'product_id' => $expensive->id,
            'sku' => 'DISABLED-'.uniqid(),
            'quantity' => 99,
            'price' => 1,
            'status' => 'disabled',
        ]);

        $this->getJson('/api/v1/collections/'.$collection->defaultUrl->slug.'?sort=price-high-low')
            ->assertOk()
            ->assertJsonPath('products.0.id', $expensive->id)
            ->assertJsonCount(1, 'products.0.skus')
            ->assertJsonPath('products.0.skus.0.status', 'published');
    }

    private function createCollection(...$products): Collection
    {
        $group = CollectionGroup::firstOrCreate(['handle' => 'main'], ['name' => 'Main']);
        $collection = Collection::create([
            'collection_group_id' => $group->id,
            'attribute_data' => ['name' => new Text('Test Collection')],
        ]);

        foreach ($products as $position => $product) {
            $collection->products()->attach($product->id, ['position' => $position]);
        }

        Url::create([
            'slug' => 'api-test-collection-'.uniqid(),
            'element_type' => $collection->getMorphClass(),
            'element_id' => $collection->id,
            'default' => true,
            'language_id' => Language::getDefault()->id,
        ]);

        return $collection->fresh(['urls']);
    }
}
