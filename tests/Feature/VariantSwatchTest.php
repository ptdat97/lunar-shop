<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Lunar\Core\Enums\ProductOptionType;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\ProductVariant;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\VariantImages;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
 * Per-variant images are Lunar's own (`ProductVariant::images()`): an ordered
 * selection from the product's gallery, whose files live once in the library
 * (modules/Assets, LibraryLinks) — so sharing a photo across sizes and colours
 * copies nothing.
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
     * A variant's images keep the order they were set in — the first is the
     * variant's primary, which is what Lunar's getThumbnail() reads.
     */
    public function test_a_variants_images_keep_their_order_and_primary(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $first = $product->addMedia(UploadedFile::fake()->image('one.png', 40, 40))->toMediaCollection('images');
        $second = $product->addMedia(UploadedFile::fake()->image('two.png', 40, 40))->toMediaCollection('images');

        $variant = $product->variants->first();
        app(VariantImages::class)->syncVariants([$variant], collect([$second, $first]));

        $fresh = $variant->fresh();

        $this->assertSame([$second->id, $first->id], $fresh->images->pluck('id')->all());
        $this->assertSame($second->id, $fresh->getThumbnail()?->id);
    }

    /** Dropping a photo from one variant keeps it on the other and in the gallery. */
    public function test_a_photo_is_kept_when_dropped_from_one_variant(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $photo = $product->addMedia(UploadedFile::fake()->image('shared.png', 40, 40))->toMediaCollection('images');

        $a = $product->variants->first();
        $b = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SW-B-'.uniqid(),
            'tax_class_id' => $a->tax_class_id,
            'enabled' => true,
        ]);

        $images = app(VariantImages::class);
        $images->syncVariants([$a, $b], collect([$photo]));
        $images->syncVariants([$a], collect());

        $this->assertSame([], $a->fresh()->images->pluck('id')->all());
        $this->assertSame([$photo->id], $b->fresh()->images->pluck('id')->all());
        $this->assertNotNull(Media::find($photo->id), 'the gallery keeps the photo for everyone else');
    }
}
