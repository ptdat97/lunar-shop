<?php

namespace Tests\Feature;

use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Language;
use Lunar\Models\Url;
use Modules\Content\Models\Page;
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

    /**
     * The sitemap pulls CMS pages through ContentService (Content owns the
     * "is it public?" rule). Unpublished pages must never leak into it.
     */
    public function test_sitemap_lists_published_pages_only(): void
    {
        $this->seedBaseData();

        Page::create([
            'title' => 'Shipping Policy', 'slug' => 'shipping-policy',
            'content' => '<p>…</p>', 'published' => true,
        ]);
        Page::create([
            'title' => 'Secret Draft', 'slug' => 'secret-draft',
            'content' => '<p>…</p>', 'published' => false,
        ]);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/pages/shipping-policy', $xml);
        $this->assertStringNotContainsString('secret-draft', $xml, 'unpublished page leaked');
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
    private function createCollectionWithProduct(): Collection
    {
        $product = $this->createProduct(['name' => 'In Collection', 'slug' => 'in-collection']);

        $group = CollectionGroup::firstOrCreate(['handle' => 'main'], ['name' => 'Main']);
        $collection = Collection::create([
            'collection_group_id' => $group->id,
            'attribute_data' => ['name' => new Text('Test Collection')],
        ]);
        $collection->products()->attach($product->id, ['position' => 1]);

        Url::create([
            'slug' => 'test-collection',
            'element_type' => $collection->getMorphClass(),
            'element_id' => $collection->id,
            'default' => true,
            'language_id' => Language::getDefault()->id,
        ]);

        return $collection->fresh(['urls']);
    }

    public function test_cms_page_renders_with_webpage_and_breadcrumb_jsonld(): void
    {
        $this->seedBaseData();

        Page::create([
            'title' => 'About Us',
            'slug' => 'about-us',
            'content' => '<p>Our story.</p>',
            'meta_title' => 'About',
            'meta_description' => 'Learn about us',
            'published' => true,
        ]);

        $html = $this->get('/pages/about-us')
            ->assertOk()
            ->assertSee('Our story.', false)
            ->getContent();

        $this->assertStringContainsString('"WebPage"', $html);
        $this->assertStringContainsString('"BreadcrumbList"', $html);
        $this->assertStringContainsString('About Us', $html);
    }

    public function test_unpublished_cms_page_is_404(): void
    {
        $this->seedBaseData();

        Page::create([
            'title' => 'Draft', 'slug' => 'draft-page',
            'content' => 'x', 'published' => false,
        ]);

        $this->get('/pages/draft-page')->assertNotFound();
    }
}
