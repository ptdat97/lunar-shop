<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\SkuBuilderService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

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

    public function test_option_groups_expose_text_color_and_image_display_types(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        // Image swatch: a real media id in the swatch collection.
        $media = $product->addMedia(UploadedFile::fake()->image('stripe.png', 400, 400))
            ->toMediaCollection('swatch');

        $variables = [
            ['name' => ['en' => 'Color'], 'display_type' => 'color', 'values' => [
                ['name' => ['en' => 'Black'], 'color' => '#111111'],
            ]],
            ['name' => ['en' => 'Pattern'], 'display_type' => 'image', 'values' => [
                ['name' => ['en' => 'Stripe'], 'image' => $media->id],
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

        // Image swatch resolves to the small `swatch` conversion, not the original.
        $this->assertSame('image', $groups['Pattern']['display_type']);
        $this->assertStringContainsString('-swatch.', $groups['Pattern']['values'][0]['image']);

        $this->assertSame('text', $groups['Size']['display_type']);
        $this->assertNull($groups['Size']['values'][0]['color']);
        $this->assertNull($groups['Size']['values'][0]['image']);
    }

    public function test_uploaded_swatch_is_ingested_into_media_and_blob_stores_the_id(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        // Simulate the FileUpload temp file on the media disk (a real image so
        // Spatie can generate the conversion on ingest).
        Storage::disk('media')->putFileAs(
            'variant-swatches',
            UploadedFile::fake()->image('temp.png', 300, 300),
            'temp.png',
        );

        [$svc, $skus] = $this->builder();
        $variables = [
            ['name' => ['en' => 'Pattern'], 'display_type' => 'image', 'values' => [
                ['name' => ['en' => 'Stripe'], 'image' => 'variant-swatches/temp.png'],
            ]],
        ];
        $svc->save($product, $variables, $skus($variables));

        $image = $product->fresh()->variables[0]['values'][0]['image'];
        $this->assertIsInt($image);                          // path → media id
        $this->assertSame(1, $product->fresh()->getMedia('swatch')->count());
        $this->assertFalse(Storage::disk('media')->exists('variant-swatches/temp.png')); // moved
    }

    public function test_removing_a_swatch_deletes_its_media(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $media = $product->addMedia(UploadedFile::fake()->image('s.png', 200, 200))->toMediaCollection('swatch');

        [$svc, $skus] = $this->builder();
        $keep = [['name' => ['en' => 'P'], 'display_type' => 'image', 'values' => [['name' => ['en' => 'S'], 'image' => $media->id]]]];
        $svc->save($product, $keep, $skus($keep));
        $this->assertSame(1, $product->fresh()->getMedia('swatch')->count());

        // Blank the image → the orphaned media is deleted.
        $drop = [['name' => ['en' => 'P'], 'display_type' => 'image', 'values' => [['name' => ['en' => 'S'], 'image' => null]]]];
        $svc->save($product->fresh(), $drop, $skus($drop));

        $this->assertNull($product->fresh()->variables[0]['values'][0]['image']);
        $this->assertSame(0, $product->fresh()->getMedia('swatch')->count());
    }

    public function test_legacy_is_image_flag_maps_to_image_display_type(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['variables' => [
            ['name' => ['en' => 'Pattern'], 'isImage' => true, 'values' => [
                ['name' => ['en' => 'Stripe'], 'image' => 'https://cdn.example.com/s.png'],
            ]],
        ]]);

        $groups = app(ProductService::class)->optionGroups($product->fresh());

        $this->assertSame('image', $groups['Pattern']['display_type']);
        $this->assertSame('https://cdn.example.com/s.png', $groups['Pattern']['values'][0]['image']);
    }
}
