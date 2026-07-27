<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Lunar\Models\Asset;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\SkuBuilderService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Variant image-swatches and per-SKU images are picked from the shared Media
 * Library (modules/Assets) via MediaPicker — the builder posts Lunar Asset
 * ids directly, never a raw upload path. SkuBuilderService therefore has no
 * ingest/hydrate step for these fields any more: it persists whatever Asset
 * id the form posts, as-is.
 */
class VariantSwatchTest extends TestCase
{
    use CreatesStorefrontData;

    /** @return array{0: SkuBuilderService, 1: callable} */
    private function builder(): array
    {
        $svc = app(SkuBuilderService::class);
        $skus = fn (array $variables) => collect($svc->combinations($variables))
            ->map(fn ($c, $i) => ['variants' => $c, 'sku' => 'SW-'.$i.uniqid(), 'price' => 1000, 'quantity' => 5, 'status' => 'published'])
            ->all();

        return [$svc, $skus];
    }

    /** A library Asset with a real image file attached. */
    private function libraryAsset(string $name = 'stripe.png'): Asset
    {
        $asset = Asset::create([]);
        $asset->addMedia(UploadedFile::fake()->image($name, 400, 400))
            ->preservingOriginal()
            ->toMediaCollection(config('lunar.media.collection', 'images'));

        return $asset->fresh();
    }

    public function test_option_groups_expose_text_color_and_image_display_types(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        // Image swatch: a library Asset id (picked via MediaPicker).
        $asset = $this->libraryAsset();

        $variables = [
            ['name' => ['en' => 'Color'], 'display_type' => 'color', 'values' => [
                ['name' => ['en' => 'Black'], 'color' => '#111111'],
            ]],
            ['name' => ['en' => 'Pattern'], 'display_type' => 'image', 'values' => [
                ['name' => ['en' => 'Stripe'], 'image' => $asset->id],
            ]],
            ['name' => ['en' => 'Size'], 'display_type' => 'text', 'values' => [
                ['name' => ['en' => 'S']],
            ]],
        ];
        [$svc, $skus] = $this->builder();
        $svc->save($product, $variables, $skus($variables));

        $groups = app(ProductService::class)->optionGroups($product->fresh());

        $this->assertSame('color', $groups['Color']['display_type']);
        $this->assertSame('#111111', $groups['Color']['values'][0]['color']);
        $this->assertNull($groups['Color']['values'][0]['image']);

        // Image swatch resolves to the small `thumb` conversion, not the original.
        $this->assertSame('image', $groups['Pattern']['display_type']);
        $this->assertStringContainsString('-thumb.', $groups['Pattern']['values'][0]['image']);

        $this->assertSame('text', $groups['Size']['display_type']);
        $this->assertNull($groups['Size']['values'][0]['color']);
        $this->assertNull($groups['Size']['values'][0]['image']);
    }

    public function test_saved_swatch_asset_id_is_persisted_as_is(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $asset = $this->libraryAsset('temp.png');

        [$svc, $skus] = $this->builder();
        $variables = [
            ['name' => ['en' => 'Pattern'], 'display_type' => 'image', 'values' => [
                ['name' => ['en' => 'Stripe'], 'image' => $asset->id],
            ]],
        ];
        $svc->save($product, $variables, $skus($variables));

        $this->assertSame($asset->id, $product->fresh()->variables[0]['values'][0]['image']);
    }

    public function test_deleting_the_library_asset_makes_the_swatch_resolve_to_null(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $asset = $this->libraryAsset('s.png');

        [$svc, $skus] = $this->builder();
        $variables = [['name' => ['en' => 'P'], 'display_type' => 'image', 'values' => [['name' => ['en' => 'S'], 'image' => $asset->id]]]];
        $svc->save($product, $variables, $skus($variables));

        // The library Asset is NOT owned by the product — SkuBuilderService
        // must never delete it just because a value is blanked or re-saved.
        $blank = [['name' => ['en' => 'P'], 'display_type' => 'image', 'values' => [['name' => ['en' => 'S'], 'image' => null]]]];
        $svc->save($product->fresh(), $blank, $skus($blank));

        $this->assertNull($product->fresh()->variables[0]['values'][0]['image']);
        $this->assertNotNull(Asset::find($asset->id), 'the library asset must survive a value being blanked');
    }

    // ---- per-SKU images ---------------------------------------------------

    public function test_a_skus_images_are_persisted_as_the_posted_asset_ids(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $asset = $this->libraryAsset('front.png');

        [$svc, $skus] = $this->builder();
        $variables = [['name' => ['en' => 'Size'], 'display_type' => 'text', 'values' => [
            ['name' => ['en' => 'S']],
        ]]];
        $rows = $skus($variables);
        $rows[0]['images'] = [$asset->id];
        $svc->save($product, $variables, $rows);

        $sku = $product->fresh()->skus()->first();
        $this->assertSame([$asset->id], $sku->images);
    }

    public function test_a_skus_asset_is_kept_and_not_deleted_when_dropped_from_another_sku(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $asset = $this->libraryAsset('shared.png');

        [$svc, $skus] = $this->builder();
        $variables = [['name' => ['en' => 'Size'], 'display_type' => 'text', 'values' => [
            ['name' => ['en' => 'S']], ['name' => ['en' => 'M']],
        ]]];
        $rows = $skus($variables);
        $rows[0]['images'] = [$asset->id];
        $rows[1]['images'] = [$asset->id];
        $svc->save($product, $variables, $rows);

        $product = $product->fresh();
        $this->assertSame([$asset->id], $product->skus()->where('sku', $rows[0]['sku'])->first()->images);
        $this->assertSame([$asset->id], $product->skus()->where('sku', $rows[1]['sku'])->first()->images);

        // Re-save with the image removed from one SKU only — the shared library
        // Asset must survive, since Catalog never owns or deletes it.
        $rows2 = $skus($variables);
        $rows2[0]['sku'] = $rows[0]['sku'];
        $rows2[1]['sku'] = $rows[1]['sku'];
        $rows2[0]['images'] = [];
        $rows2[1]['images'] = [$asset->id];
        $svc->save($product->fresh(), $variables, $rows2);

        $product = $product->fresh();
        $this->assertSame([], $product->skus()->where('sku', $rows[0]['sku'])->first()->images);
        $this->assertSame([$asset->id], $product->skus()->where('sku', $rows[1]['sku'])->first()->images);
        $this->assertNotNull(Asset::find($asset->id), 'shared library asset must not be deleted');
    }
}
