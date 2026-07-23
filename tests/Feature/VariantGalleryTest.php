<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Product;
use Modules\Catalog\Http\Resources\ProductSkuResource;
use Modules\Catalog\Models\ProductSku;
use Modules\Catalog\Services\ProductService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Per-colour product galleries: each SKU may carry its own subset of the
 * product's media (the `images` JSON column, a list of media ids), so choosing
 * a colour swaps the gallery.
 *
 * Covers the two halves that have to agree: the SSR gallery (the media view
 * composer scopes it to the selected variant) and the hydration payload
 * (ProductSkuResource resolves ids into the same shape as the product gallery,
 * which is what enhance/product-variant.js swaps in).
 */
class VariantGalleryTest extends TestCase
{
    use CreatesStorefrontData;

    /** Attach $count images to a product and return their media ids in order. */
    private function attachImages(Product $product, int $count): array
    {
        Storage::fake('public');

        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $ids[] = $product
                ->addMedia(UploadedFile::fake()->image("shot-{$i}.jpg", 800, 1200))
                ->preservingOriginal()
                ->toMediaCollection(config('lunar.media.collection', 'images'))
                ->id;
        }

        return $ids;
    }

    public function test_sku_images_resolve_to_the_product_gallery_shape(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $ids = $this->attachImages($product, 2);

        $sku = $product->skus()->first();
        $sku->update(['images' => $ids]);

        $product->load('media');
        $sku->setRelation('product', $product);

        $payload = (new ProductSkuResource($sku))->toArray(request());

        $this->assertCount(2, $payload['images']);
        // The gallery renderer needs these keys; a raw id list would break it.
        $this->assertArrayHasKey('large', $payload['images'][0]);
        $this->assertArrayHasKey('small', $payload['images'][0]);
        $this->assertArrayHasKey('zoom', $payload['images'][0]);
        $this->assertSame($ids[0], $payload['images'][0]['id']);
    }

    public function test_sku_image_order_is_preserved_not_media_order(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $ids = $this->attachImages($product, 3);

        // Lead with the LAST media item — the whole point of per-colour sets.
        $reordered = [$ids[2], $ids[0], $ids[1]];

        $sku = $product->skus()->first();
        $sku->update(['images' => $reordered]);

        $product->load('media');
        $sku->setRelation('product', $product);

        $payload = (new ProductSkuResource($sku))->toArray(request());

        $this->assertSame(
            $reordered,
            array_column($payload['images'], 'id'),
            'the SKU\'s own ordering must survive — filtering by media order would undo it',
        );
    }

    public function test_a_sku_without_images_falls_back_to_the_product_gallery(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $this->attachImages($product, 2);

        $sku = $product->skus()->first();
        $sku->update(['images' => []]);

        $product->load('media');
        $sku->setRelation('product', $product);

        $payload = (new ProductSkuResource($sku))->toArray(request());

        // Empty here is the contract: the storefront then shows state.images.
        $this->assertSame([], $payload['images']);
    }

    public function test_ssr_gallery_renders_the_selected_variants_images(): void
    {
        $this->seedBaseData();

        $variables = [
            [
                'name' => ['en' => 'Color'],
                'display_type' => 'color',
                'values' => [
                    ['name' => ['en' => 'Black'], 'color' => '#000000'],
                    ['name' => ['en' => 'White'], 'color' => '#ffffff'],
                ],
            ],
        ];

        $product = $this->createProduct([
            'slug' => 'gallery-tee',
            'variables' => $variables,
            'variant_indexes' => ['0'],
            'sku' => 'GAL-BLACK',
        ]);

        $ids = $this->attachImages($product, 2);

        // Black leads with the first image, White with the second.
        $product->skus()->first()->update(['images' => [$ids[0]]]);

        ProductSku::create([
            'product_id' => $product->id,
            'sku' => 'GAL-WHITE',
            'variants' => ['1'],
            'quantity' => 5,
            'price' => 1999,
            'images' => [$ids[1]],
            'is_default' => false,
            'status' => 'published',
        ]);

        $service = app(ProductService::class);
        $fresh = $service->findBySlug('gallery-tee');

        $black = $service->resolveSelectedVariant($fresh, ['Color' => 'Black']);
        $white = $service->resolveSelectedVariant($fresh, ['Color' => 'White']);

        $this->assertSame('GAL-BLACK', $black->sku);
        $this->assertSame('GAL-WHITE', $white->sku);
        $this->assertNotSame(
            $black->images,
            $white->images,
            'each colour must own a distinct image set, else the gallery never changes',
        );
    }

    public function test_serializing_many_skus_costs_no_extra_media_queries(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'nplusone-tee']);
        $ids = $this->attachImages($product, 2);

        foreach (range(1, 4) as $i) {
            ProductSku::create([
                'product_id' => $product->id,
                'sku' => 'NP-'.$i,
                'variants' => [(string) $i],
                'quantity' => 3,
                'price' => 1999,
                'images' => $ids,
                'is_default' => false,
                'status' => 'published',
            ]);
        }

        $fresh = app(ProductService::class)->findBySlug('nplusone-tee');

        \DB::enableQueryLog();
        \DB::flushQueryLog();

        foreach ($fresh->skus as $sku) {
            (new ProductSkuResource($sku))->toArray(request());
        }

        $mediaQueries = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], '`media`'))
            ->count();

        \DB::disableQueryLog();

        $this->assertSame(0, $mediaQueries, 'SKU images must resolve off the already-loaded product media');
    }

    /**
     * The multi-product endpoints (bySlugs/byIds/related/search) are separate
     * query builders from findBySlug. They regressed once already: a single
     * GET /api/v1/products?slugs=… fired 297 statements, 263 of them duplicate
     * `lunar_products` and `media` lookups, one pair per SKU.
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
            $ids = $this->attachImages($product, 2);
            $slugs[] = "bulk-tee-{$p}";

            // Several SKUs each, so an N+1 would be unmistakable.
            foreach (range(1, 4) as $i) {
                ProductSku::create([
                    'product_id' => $product->id,
                    'sku' => "BULK-{$p}-{$i}",
                    'variants' => [(string) $i],
                    'quantity' => 5,
                    'price' => 1999,
                    'images' => $ids,
                    'is_default' => false,
                    'status' => 'published',
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

        $repeatedMedia = $log
            ->filter(fn ($q) => str_contains($q['query'], 'from `media` where `id` in'))
            ->count();

        $this->assertSame(0, $repeatedProduct, 'each SKU must reuse the parent product that loaded it');
        $this->assertSame(0, $repeatedMedia, 'SKU images must resolve off the already-loaded product media');
    }
}
