<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * SEO surfaces: the storefront sitemap (machine endpoint) and the collection
 * page's JSON-LD (ItemList + BreadcrumbList). The sitemap reads Lunar Urls by
 * morph alias — this locks in that products/collections actually appear (a
 * class-name match would silently emit an almost-empty sitemap).
 */
class SeoTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_sitemap_lists_published_products(): void
    {
        $this->seedBaseData();
        $this->createProduct(['name' => 'Sitemap Tee', 'slug' => 'sitemap-tee']);

        $res = $this->get('/sitemap.xml')->assertOk();
        $res->assertHeader('Content-Type', 'application/xml');

        $xml = $res->getContent();
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString('/products/sitemap-tee', $xml);
        $this->assertStringContainsString('<changefreq>', $xml);

        // Well-formed XML.
        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap must be valid XML');
    }

    public function test_collection_page_emits_itemlist_jsonld(): void
    {
        $this->seedBaseData();
        $collection = $this->createCollectionWithProduct();

        $this->get('/collections/'.$collection->defaultUrl->slug)
            ->assertOk()
            ->assertSee('"@type":"ItemList"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    /**
     * Minimal collection with one published product attached + a default Url.
     */
    private function createCollectionWithProduct(): \Lunar\Models\Collection
    {
        $product = $this->createProduct(['name' => 'In Collection', 'slug' => 'in-collection']);

        $group = \Lunar\Models\CollectionGroup::firstOrCreate(['handle' => 'main'], ['name' => 'Main']);
        $collection = \Lunar\Models\Collection::create([
            'collection_group_id' => $group->id,
            'attribute_data' => ['name' => new \Lunar\FieldTypes\Text('Test Collection')],
        ]);
        $collection->products()->attach($product->id, ['position' => 1]);

        \Lunar\Models\Url::create([
            'slug' => 'test-collection',
            'element_type' => $collection->getMorphClass(),
            'element_id' => $collection->id,
            'default' => true,
            'language_id' => \Lunar\Models\Language::getDefault()->id,
        ]);

        return $collection->fresh(['urls']);
    }
}
