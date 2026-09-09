<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Lunar\Core\Enums\ProductOptionType;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\ProductVariant;
use Modules\Catalog\Services\ProductService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Swatches: what the storefront picker renders next to an option value.
 *
 * Lunar 2.0 owns this now — `ProductOption.type` says how a value is rendered
 * (`text` / `colour` / `swatch`) and the value's own `meta` carries the payload.
 * The storefront keeps its own vocabulary (`text` / `color` / `image`), so these
 * tests pin the translation between the two as well as the payload itself.
 *
 * Per-variant images stay a list of Media Library Asset ids picked from the
 * shared library (modules/Assets) rather than media the variant owns: the
 * catalogue reuses 162 assets across 1,945 references, so owning them would copy
 * the same files over and over.
 */
class VariantSwatchTest extends TestCase
{
    use CreatesStorefrontData;

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
        $product = $this->createProduct(['options' => ['Size' => 'S']]);
        $variant = $product->variants->first();

        $asset = $this->libraryAsset();

        $colour = $this->optionValue($product, 'Color', 'Black');
        $colour->option->forceFill(['type' => ProductOptionType::Colour->value])->save();
        $colour->forceFill(['meta' => ['colour' => '#111111']])->save();

        $pattern = $this->optionValue($product, 'Pattern', 'Stripe');
        $pattern->option->forceFill(['type' => ProductOptionType::Swatch->value])->save();
        $pattern->forceFill(['meta' => ['image' => $asset->id]])->save();

        $variant->values()->syncWithoutDetaching([$colour->id, $pattern->id]);

        $groups = app(ProductService::class)->optionGroups($product->fresh(['variants.values', 'productOptions.values']));

        // en-GB `colour` on the option becomes the storefront's `color`.
        $this->assertSame('color', $groups['Color']['display_type']);
        $this->assertSame('#111111', $groups['Color']['values'][0]['color']);
        $this->assertNull($groups['Color']['values'][0]['image']);

        // `swatch` becomes `image`, resolved to the small conversion rather than
        // the heavy original.
        $this->assertSame('image', $groups['Pattern']['display_type']);
        $this->assertStringContainsString('-thumb.', $groups['Pattern']['values'][0]['image']);

        $this->assertSame('text', $groups['Size']['display_type']);
        $this->assertNull($groups['Size']['values'][0]['color']);
        $this->assertNull($groups['Size']['values'][0]['image']);
    }

    /** A swatch whose library Asset was deleted must degrade, not break. */
    public function test_deleting_the_library_asset_makes_the_swatch_resolve_to_null(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $variant = $product->variants->first();

        $asset = $this->libraryAsset();

        $pattern = $this->optionValue($product, 'Pattern', 'Stripe');
        $pattern->option->forceFill(['type' => ProductOptionType::Swatch->value])->save();
        $pattern->forceFill(['meta' => ['image' => $asset->id]])->save();
        $variant->values()->syncWithoutDetaching([$pattern->id]);

        $asset->delete();

        $groups = app(ProductService::class)->optionGroups($product->fresh(['variants.values', 'productOptions.values']));

        $this->assertNull($groups['Pattern']['values'][0]['image']);
    }

    /**
     * A variant's images are stored exactly as posted — Asset ids, in order.
     *
     * The picker posts library ids, so there is no ingest step to get wrong; the
     * risk is the opposite, that something helpfully "resolves" them on save and
     * the reference stops surviving a file replacement.
     */
    public function test_a_variants_images_are_persisted_as_the_posted_asset_ids(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        $first = $this->libraryAsset('one.png');
        $second = $this->libraryAsset('two.png');

        $variant = $product->variants->first();
        $variant->update(['image_asset_ids' => [$second->id, $first->id]]);

        $this->assertSame([$second->id, $first->id], $variant->fresh()->image_asset_ids);
    }

    /** Dropping an image from one variant must not delete the shared asset. */
    public function test_an_asset_is_kept_when_dropped_from_one_variant(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $asset = $this->libraryAsset();

        $a = $product->variants->first();
        $a->update(['image_asset_ids' => [$asset->id]]);

        $b = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SW-B-'.uniqid(),
            'image_asset_ids' => [$asset->id],
            'tax_class_id' => $a->tax_class_id,
            'enabled' => true,
        ]);

        $a->update(['image_asset_ids' => []]);

        $this->assertSame([$asset->id], $b->fresh()->image_asset_ids);
        $this->assertNotNull(Asset::find($asset->id), 'the library keeps the file for everyone else');
    }
}
