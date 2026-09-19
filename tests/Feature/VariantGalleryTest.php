<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Modules\Catalog\Http\Resources\ProductVariantResource;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\VariantImages;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Per-colour product galleries: each variant may show its own ordered subset of
 * the product's gallery — Lunar's variant images (`ProductVariant::images()`,
 * the `media_product_variant` pivot) — so choosing a colour swaps the gallery.
 *
 * Covers the two halves that have to agree: the SSR gallery (the media view
 * composer scopes it to the selected variant) and the hydration payload
 * (ProductVariantResource serialises them in the same shape as the product
 * gallery, which is what enhance/product-variant.js swaps in).
 */
class VariantGalleryTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    /** Add $count photos to the product's own gallery and return them in order. */
    private function galleryPhotos(Product $product, int $count): array
    {
        $media = [];
        for ($i = 1; $i <= $count; $i++) {
            $media[] = $product->addMedia(UploadedFile::fake()->image("shot-{$i}.jpg", 800, 1200))
                ->toMediaCollection(config('lunar.media.collection', 'images'));
        }

        return $media;
    }

    /** @param  array<int, Media>  $media */
    private function showOn(ProductVariant $variant, array $media): void
    {
        app(VariantImages::class)->syncVariants([$variant], collect($media));
    }

    public function test_sku_images_resolve_to_the_product_gallery_shape(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $photos = $this->galleryPhotos($product, 2);

        $sku = $product->variants()->first();
        $this->showOn($sku, $photos);

        $payload = (new ProductVariantResource($sku->fresh()))->toArray(request());

        $this->assertCount(2, $payload['images']);
        // The gallery renderer needs these keys; a raw id list would break it.
        $this->assertArrayHasKey('large', $payload['images'][0]);
        $this->assertArrayHasKey('small', $payload['images'][0]);
        $this->assertArrayHasKey('zoom', $payload['images'][0]);
    }

    public function test_sku_image_order_is_preserved_not_gallery_order(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        [$a, $b, $c] = $this->galleryPhotos($product, 3);

        // Lead with the LAST gallery photo — the whole point of per-colour sets.
        $sku = $product->variants()->first();
        $this->showOn($sku, [$c, $a, $b]);

        $payload = (new ProductVariantResource($sku->fresh()))->toArray(request());

        $this->assertSame(
            [$c->id, $a->id, $b->id],
            array_column($payload['images'], 'id'),
            'the variant\'s own ordering must survive — gallery order would undo it',
        );
    }

    public function test_a_sku_without_images_falls_back_to_the_product_gallery(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $this->galleryPhotos($product, 2);

        $payload = (new ProductVariantResource($product->variants()->first()))->toArray(request());

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

        [$black, $white] = $this->galleryPhotos($product, 2);

        $this->showOn($product->variants()->first(), [$black]);

        $whiteVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'GAL-WHITE',
            'tax_class_id' => TaxClass::getDefault()?->id,
            'enabled' => true,
        ]);
        $whiteVariant->values()->syncWithoutDetaching([
            $this->optionValue($product, 'Color', 'White')->id,
        ]);
        $this->showOn($whiteVariant, [$white]);

        $service = app(ProductService::class);
        $fresh = $service->findBySlug('gallery-tee');

        $blackPick = $service->resolveSelectedVariant($fresh, ['Color' => 'Black']);
        $whitePick = $service->resolveSelectedVariant($fresh, ['Color' => 'White']);

        $this->assertSame('GAL-BLACK', $blackPick->sku);
        $this->assertSame('GAL-WHITE', $whitePick->sku);
        $this->assertSame([$black->id], $blackPick->images->pluck('id')->all());
        $this->assertSame([$white->id], $whitePick->images->pluck('id')->all());
    }

    /**
     * The product page loads every variant's images with the product
     * (ProductService eager-loads `variants.images`), so serialising many
     * variants must not query the pivot or the media table again.
     */
    public function test_serializing_many_skus_does_not_query_their_images_again(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'nplusone-tee']);
        $photos = $this->galleryPhotos($product, 2);

        foreach (range(1, 4) as $i) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'NP-'.$i,
                'tax_class_id' => TaxClass::getDefault()?->id,
                'enabled' => true,
            ]);
            $this->showOn($variant, $photos);
        }

        $fresh = app(ProductService::class)->findBySlug('nplusone-tee');

        \DB::enableQueryLog();
        \DB::flushQueryLog();

        foreach ($fresh->variants as $sku) {
            (new ProductVariantResource($sku))->toArray(request());
        }

        $imageQueries = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'media_product_variant'))
            ->count();

        \DB::disableQueryLog();

        $this->assertSame(0, $imageQueries, 'variant images must come from the eager load, not one query per variant');
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
            $photos = $this->galleryPhotos($product, 2);
            $slugs[] = "bulk-tee-{$p}";

            // Several SKUs each, so an N+1 would be unmistakable.
            foreach (range(1, 4) as $i) {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => "BULK-{$p}-{$i}",
                    'tax_class_id' => TaxClass::getDefault()?->id,
                    'enabled' => true,
                ]);
                $this->showOn($variant, $photos);
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

        // Variant images ride the same eager load: one batched query, not one per SKU.
        $this->assertLessThanOrEqual(
            1,
            $log->filter(fn ($q) => str_contains($q['query'], 'media_product_variant'))->count(),
            'variant images must be eager-loaded with the cards',
        );
    }
}
