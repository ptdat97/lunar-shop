<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Product;
use Modules\Catalog\Services\VariantImages;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The SSR gallery and the hydration payload have to show a variant's pictures
 * the same way.
 *
 * They once did not: the payload resolved one list of ids and the SSR view
 * composer another, so a picture assigned to a variant appeared only after the
 * JS re-rendered on a variant change. Both now read the same thing — Lunar's
 * variant images (`ProductVariant::images()`) — and this pins them together.
 */
class VariantGallerySsrTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    private function photo(Product $product, string $name): Media
    {
        return $product->addMedia(UploadedFile::fake()->image($name, 600, 600))
            ->toMediaCollection(config('lunar.media.collection', 'images'));
    }

    /** A variant's own picture is painted server-side, before any JS runs. */
    public function test_a_variants_picture_reaches_the_server_rendered_gallery(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['stock' => 5]);
        $this->photo($product, 'gallery.png');
        $own = $this->photo($product, 'variant-only.png');

        app(VariantImages::class)->syncVariants([$product->variants->first()], collect([$own]));

        $html = $this->get("/products/{$product->defaultUrl->slug}")->assertOk()->getContent();
        $markup = preg_replace('/<script type="application\/json".*?<\/script>/s', '', $html);

        $this->assertStringContainsString("/{$own->id}/conversions/", $markup);
    }

    public function test_the_payload_and_the_ssr_gallery_agree(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['stock' => 5]);
        $own = $this->photo($product, 'agree.png');

        app(VariantImages::class)->syncVariants([$product->variants->first()], collect([$own]));

        $html = $this->get("/products/{$product->defaultUrl->slug}")->assertOk()->getContent();

        // Compare the two by media id, not by substring: @json() escapes every
        // slash, so the payload spells the same URL differently to the markup.
        preg_match('/data-product-state>(.*?)<\/script>/s', $html, $m);
        $this->assertNotEmpty($m, 'The product state payload is missing from the page.');

        $state = json_decode(html_entity_decode($m[1]), true);

        $this->assertSame([$own->id], collect($state['variants'][0]['images'] ?? [])->pluck('id')->all());

        $markup = preg_replace('/<script type="application\/json".*?<\/script>/s', '', $html);

        $this->assertStringContainsString(
            "/{$own->id}/conversions/",
            $markup,
            'The picture is in the payload but not in the SSR markup — the two paths disagree again.',
        );
    }

    /** A variant with no pictures of its own still shows the product gallery. */
    public function test_a_sku_without_pictures_falls_back_to_the_product_gallery(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['stock' => 5]);
        $gallery = $this->photo($product, 'whole-gallery.png');

        $this->get("/products/{$product->defaultUrl->slug}")
            ->assertOk()
            ->assertSee('data-product-gallery', false)
            ->assertSee("/{$gallery->id}/conversions/", false);
    }
}
