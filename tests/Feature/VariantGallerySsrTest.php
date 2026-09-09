<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Lunar\Core\Models\Asset;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The SSR gallery and the hydration payload have to resolve a SKU's pictures the
 * same way.
 *
 * They did not. The payload went through the Lunar Asset ids
 * (ProductVariantResource::galleryImages), while the SSR view composer looked those
 * ids up in `$product->media` — so a picture picked from the library for one SKU,
 * and never attached to the product itself, was silently filtered out of the
 * server-rendered gallery.
 *
 * What that looked like: add a picture to a variant in the admin, open the
 * storefront, and it is not there — until you click to another variant and back,
 * because that is when the JS re-renders from the payload, which had it all
 * along.
 *
 * Mutation check: resolve $scoped from `$media->keyBy('id')` again and
 * `test_a_library_only_picture_reaches_the_server_rendered_gallery` goes red.
 */
class VariantGallerySsrTest extends TestCase
{
    use CreatesStorefrontData;

    /** A library Asset with a real image, attached to nothing else. */
    private function libraryAsset(string $name): Asset
    {
        $asset = Asset::create([]);
        $asset->addMedia(UploadedFile::fake()->image($name, 600, 600))
            ->preservingOriginal()
            ->toMediaCollection(config('lunar.media.collection', 'images'));

        return $asset->fresh();
    }

    /**
     * The regression: a picture that exists only in the library, assigned to the
     * SKU, must render server-side on first paint.
     */
    public function test_a_library_only_picture_reaches_the_server_rendered_gallery(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['stock' => 5]);
        $asset = $this->libraryAsset('variant-only.png');

        $sku = $product->variants->first();
        $sku->update(['image_asset_ids' => [$asset->id]]);

        $slug = $product->defaultUrl->slug;

        $html = $this->get("/products/{$slug}")->assertOk()->getContent();

        // The conversion URL carries the media id, which is how the picture is
        // identifiable in the markup.
        $mediaId = $asset->fresh()->file?->id;

        $this->assertNotNull($mediaId, 'The library asset has no media file.');

        $this->assertStringContainsString(
            "/{$mediaId}/",
            $html,
            'A picture assigned to the SKU from the library never reached the SSR gallery — '
            .'it only appears after the JS re-renders on a variant change.',
        );
    }

    /** The payload has always had it; this pins the two together. */
    public function test_the_payload_and_the_ssr_gallery_agree(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['stock' => 5]);
        $asset = $this->libraryAsset('agree.png');

        $product->variants->first()->update(['image_asset_ids' => [$asset->id]]);

        $slug = $product->defaultUrl->slug;
        $html = $this->get("/products/{$slug}")->assertOk()->getContent();

        $mediaId = $asset->fresh()->file?->id;

        // Compare the two by media id, not by substring: @json() escapes every
        // slash, so the payload spells the same URL differently to the markup.
        preg_match('/data-product-state>(.*?)<\/script>/s', $html, $m);
        $this->assertNotEmpty($m, 'The product state payload is missing from the page.');

        $state = json_decode(html_entity_decode($m[1]), true);
        $payloadIds = collect($state['variants'][0]['images'] ?? [])->pluck('id')->all();

        $this->assertSame([$mediaId], $payloadIds, 'The payload lost the picture.');

        // And the same id has to be in the markup painted before any JS runs.
        $markup = preg_replace('/<script type="application\/json".*?<\/script>/s', '', $html);

        $this->assertStringContainsString(
            "/media/{$mediaId}/",
            $markup,
            'The picture is in the payload but not in the SSR markup — the two paths disagree again.',
        );
    }

    /** A SKU with no pictures of its own still falls back to the product gallery. */
    public function test_a_sku_without_pictures_falls_back_to_the_product_gallery(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['stock' => 5]);
        $product->variants->first()->update(['image_asset_ids' => []]);

        $slug = $product->defaultUrl->slug;

        $this->get("/products/{$slug}")
            ->assertOk()
            ->assertSee('data-product-gallery', false);
    }
}
