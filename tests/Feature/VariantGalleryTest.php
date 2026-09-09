<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Modules\Catalog\Http\Resources\ProductVariantResource;
use Modules\Catalog\Services\ProductService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Per-colour product galleries: each SKU may carry its own subset of Media
 * Library Assets (the `images` JSON column, a list of Asset ids picked via
 * MediaPicker), so choosing a colour swaps the gallery.
 *
 * Covers the two halves that have to agree: the SSR gallery (the media view
 * composer scopes it to the selected variant) and the hydration payload
 * (ProductVariantResource resolves ids into the same shape as the product gallery,
 * which is what enhance/product-variant.js swaps in).
 */
class VariantGalleryTest extends TestCase
{
    use CreatesStorefrontData;

    /** Create $count library Assets (with a real image file each) and return their ids in order. */
    private function makeAssets(int $count): array
    {
        Storage::fake('public');

        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $asset = Asset::create([]);
            $asset->addMedia(UploadedFile::fake()->image("shot-{$i}.jpg", 800, 1200))
                ->preservingOriginal()
                ->toMediaCollection(config('lunar.media.collection', 'images'));

            $ids[] = $asset->id;
        }

        return $ids;
    }

    public function test_sku_images_resolve_to_the_product_gallery_shape(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $ids = $this->makeAssets(2);

        $sku = $product->variants()->first();
        $sku->update(['images' => $ids]);

        $payload = (new ProductVariantResource($sku))->toArray(request());

        $this->assertCount(2, $payload['images']);
        // The gallery renderer needs these keys; a raw id list would break it.
        $this->assertArrayHasKey('large', $payload['images'][0]);
        $this->assertArrayHasKey('small', $payload['images'][0]);
        $this->assertArrayHasKey('zoom', $payload['images'][0]);
    }

    public function test_sku_image_order_is_preserved_not_asset_order(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $ids = $this->makeAssets(3);

        // Lead with the LAST asset — the whole point of per-colour sets.
        $reordered = [$ids[2], $ids[0], $ids[1]];

        $sku = $product->variants()->first();
        $sku->update(['images' => $reordered]);

        $payload = (new ProductVariantResource($sku))->toArray(request());

        // The payload's `id` is the underlying Media id (not the Asset id) —
        // resolve each Asset's Media id in the same order to compare.
        $expectedMediaIds = collect($reordered)
            ->map(fn (int $assetId) => Asset::find($assetId)->file->id)
            ->all();

        $this->assertSame(
            $expectedMediaIds,
            array_column($payload['images'], 'id'),
            'the SKU\'s own ordering must survive — filtering by asset order would undo it',
        );
    }

    public function test_a_sku_without_images_falls_back_to_the_product_gallery(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $this->makeAssets(2);

        $sku = $product->variants()->first();
        $sku->update(['images' => []]);

        $payload = (new ProductVariantResource($sku))->toArray(request());

        // Empty here is the contract: the storefront then shows state.images.
        $this->assertSame([], $payload['images']);
    }

    public function test_ssr_gallery_renders_the_selected_variants_images(): void
    {
        $this->seedBaseData();

        // Two colours as real option values — the shape the picker resolves
        // against since the purchasable became Lunar's ProductVariant.
        $product = $this->createProduct([
            'slug' => 'gallery-tee',
            'sku' => 'GAL-BLACK',
            'options' => ['Color' => 'Black'],
        ]);

        $ids = $this->makeAssets(2);

        // Black leads with the first asset, White with the second.
        $product->variants()->first()->update(['images' => [$ids[0]]]);

        $white = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'GAL-WHITE',
            'images' => [$ids[1]],
            'tax_class_id' => TaxClass::getDefault()?->id,
            'enabled' => true,
        ]);
        $white->values()->syncWithoutDetaching([
            $this->optionValue($product, 'Color', 'White')->id,
        ]);

        $service = app(ProductService::class);
        $fresh = $service->findBySlug('gallery-tee');

        $blackPick = $service->resolveSelectedVariant($fresh, ['Color' => 'Black']);
        $whitePick = $service->resolveSelectedVariant($fresh, ['Color' => 'White']);

        $this->assertSame('GAL-BLACK', $blackPick->sku);
        $this->assertSame('GAL-WHITE', $whitePick->sku);
        $this->assertNotSame(
            $blackPick->images,
            $whitePick->images,
            'each colour must own a distinct image set, else the gallery never changes',
        );
    }

    /**
     * Resolution goes through MediaUrl::assetMedia(), scoped per request — a
     * page rendering many SKUs must cost at most one query per DISTINCT Asset
     * id, not one per SKU. Sharing the same 2 Assets across 4 SKUs must not
     * multiply the query count.
     */
    public function test_serializing_many_skus_costs_bounded_asset_queries(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'nplusone-tee']);
        $ids = $this->makeAssets(2);

        foreach (range(1, 4) as $i) {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'NP-'.$i,
                'images' => $ids,
                'tax_class_id' => TaxClass::getDefault()?->id,
                'enabled' => true,
            ]);
        }

        $fresh = app(ProductService::class)->findBySlug('nplusone-tee');

        \DB::enableQueryLog();
        \DB::flushQueryLog();

        foreach ($fresh->variants as $sku) {
            (new ProductVariantResource($sku))->toArray(request());
        }

        $assetQueries = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], '`lunar_assets`') || str_contains(strtolower($q['query']), 'from `assets`'))
            ->count();

        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            1,
            $assetQueries,
            'the same 2 Asset ids shared across 4 SKUs must resolve in at most one query, not one per SKU',
        );
    }

    /**
     * The multi-product endpoints (bySlugs/byIds/related/search) are separate
     * query builders from findBySlug. They regressed once already: a single
     * GET /api/v1/products?slugs=… fired 297 statements, 263 of them duplicate
     * `lunar_products` lookups, one pair per SKU.
     *
     * The `skus` relation is chaperoned at its definition so every path is
     * covered; this asserts the endpoint itself, not one service method.
     */
    public function test_the_products_endpoint_does_not_n_plus_one_over_skus(): void
    {
        $this->seedBaseData();

        $slugs = [];
        foreach (range(1, 3) as $p) {
            $product = $this->createProduct(['slug' => "bulk-tee-{$p}"]);
            $ids = $this->makeAssets(2);
            $slugs[] = "bulk-tee-{$p}";

            // Several SKUs each, so an N+1 would be unmistakable.
            foreach (range(1, 4) as $i) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => "BULK-{$p}-{$i}",
                    'images' => $ids,
                    'tax_class_id' => TaxClass::getDefault()?->id,
                    'enabled' => true,
                ]);
            }
        }

        \DB::enableQueryLog();
        \DB::flushQueryLog();

        $this->getJson('/api/v1/products?slugs='.implode(',', $slugs))
            ->assertSuccessful();

        $log = collect(\DB::getQueryLog());
        \DB::disableQueryLog();

        $repeatedProduct = $log
            ->filter(fn ($q) => str_contains($q['query'], 'from `lunar_products` where `lunar_products`.`id` ='))
            ->count();

        $this->assertSame(0, $repeatedProduct, 'each SKU must reuse the parent product that loaded it');
    }
}
