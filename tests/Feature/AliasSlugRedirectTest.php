<?php

namespace Tests\Feature;

use Lunar\Core\Models\Collection;
use Lunar\Core\Models\CollectionGroup;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Url;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Alias/legacy URL slugs must 301 to the canonical URL — Lunar keeps old slugs
 * in `lunar_urls` (default = 0) exactly so past links keep working after a
 * rename. Before this behaviour was fixed a published product/collection with a
 * renamed slug would 404 on its old link (found while smoke-testing the
 * storefront E2E).
 */
class AliasSlugRedirectTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_product_page_redirects_alias_slug_to_canonical_301(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $this->createAliasUrl($product->getMorphClass(), $product->id, 'old-product-slug');

        $canonical = $product->defaultUrl->slug;

        $this->get('/products/old-product-slug')
            ->assertStatus(301)
            ->assertRedirectContains('/products/'.$canonical);
    }

    public function test_product_page_redirect_keeps_deep_link_query(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $this->createAliasUrl($product->getMorphClass(), $product->id, 'old-product-slug');

        $canonical = $product->defaultUrl->slug;

        $response = $this->get('/products/old-product-slug?color=den&size=m');
        $response->assertStatus(301)->assertRedirectContains('/products/'.$canonical);

        parse_str(
            (string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY),
            $query,
        );
        $this->assertSame('den', $query['color'] ?? null);
        $this->assertSame('m', $query['size'] ?? null);
    }

    public function test_product_api_redirects_alias_slug_to_canonical_301(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $this->createAliasUrl($product->getMorphClass(), $product->id, 'old-product-slug');

        $canonical = $product->defaultUrl->slug;

        $this->getJson('/api/v1/products/old-product-slug')
            ->assertStatus(301)
            ->assertRedirectContains('/api/v1/products/'.$canonical);
    }

    public function test_collection_page_redirects_alias_slug_to_canonical_301(): void
    {
        $this->seedBaseData();
        $collection = $this->createCollectionFixture();
        $this->createAliasUrl($collection->getMorphClass(), $collection->id, 'old-collection-slug');

        $canonical = $collection->defaultUrl->slug;

        $this->get('/collections/old-collection-slug')
            ->assertStatus(301)
            ->assertRedirectContains('/collections/'.$canonical);
    }

    public function test_collection_api_redirects_alias_slug_to_canonical_301(): void
    {
        $this->seedBaseData();
        $collection = $this->createCollectionFixture();
        $this->createAliasUrl($collection->getMorphClass(), $collection->id, 'old-collection-slug');

        $canonical = $collection->defaultUrl->slug;

        $this->getJson('/api/v1/collections/old-collection-slug')
            ->assertStatus(301)
            ->assertRedirectContains('/api/v1/collections/'.$canonical);
    }

    public function test_unknown_slug_still_404s(): void
    {
        $this->seedBaseData();

        $this->get('/products/does-not-exist')->assertNotFound();
        $this->get('/collections/does-not-exist')->assertNotFound();
        $this->getJson('/api/v1/products/does-not-exist')->assertNotFound();
        $this->getJson('/api/v1/collections/does-not-exist')->assertNotFound();
    }

    private function createAliasUrl(string $elementType, int $elementId, string $slug): Url
    {
        return Url::create([
            'slug' => $slug,
            'element_type' => $elementType,
            'element_id' => $elementId,
            'default' => false,
            'language_id' => Language::getDefault()->id,
        ]);
    }

    private function createCollectionFixture(): Collection
    {
        $group = CollectionGroup::firstOrCreate(['handle' => 'main'], ['name' => 'Main']);
        $collection = Collection::create([
            'collection_group_id' => $group->id,
            'name' => ['en' => 'Redirect Test Collection'],
        ]);

        Url::create([
            'slug' => 'redirect-test-collection-'.uniqid(),
            'element_type' => $collection->getMorphClass(),
            'element_id' => $collection->id,
            'default' => true,
            'language_id' => Language::getDefault()->id,
        ]);

        return $collection->fresh(['urls']);
    }
}
