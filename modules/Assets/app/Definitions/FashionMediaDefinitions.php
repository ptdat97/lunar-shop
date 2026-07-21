<?php

namespace Modules\Assets\Definitions;

use Lunar\Base\StandardMediaDefinitions;
use Modules\Assets\Services\MediaSettings;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Extends Lunar's StandardMediaDefinitions (inherited — not replaced) to add
 * fashion-friendly responsive sizes plus a WebP conversion for the storefront.
 *
 * NOTE: Spatie's MediaCollection::registerMediaConversions() stores a single
 * closure, so a second call would overwrite the parent's. We therefore register
 * Lunar's standard sizes (zoom/large/medium) AND our extras in one closure.
 */
class FashionMediaDefinitions extends StandardMediaDefinitions
{
    /**
     * The admin-facing `small` conversion (parent registers it model-level).
     * We override so its size also comes from MediaSettings.
     *
     * Uses Fit::Crop (not Fill): the source is scaled to cover the target box
     * and cropped to the exact aspect ratio. Unlike Fill, a source smaller than
     * the target is scaled up to fill the frame instead of being padded with a
     * white canvas — so we never produce a tiny image floating on white.
     */
    /** A small square swatch conversion (chip images). */
    public const SWATCH_COLLECTION = 'swatch';

    /** Conversion name used for storefront swatch chips. */
    public const SWATCH_CONVERSION = 'swatch';

    public const SWATCH_SIZE = 160;

    public function registerMediaConversions(HasMedia $model, ?Media $media = null): void
    {
        $small = app(MediaSettings::class)->sizes()['small'];

        $model->addMediaConversion('small')
            ->fit(Fit::Crop, $small['width'], $small['height'])
            ->sharpen(10)
            ->keepOriginalImageFormat();
    }

    /**
     * Add a `swatch` media collection alongside Lunar's `images`. Variant option
     * swatches (colour/pattern chips) live here so they get their own small,
     * square conversions instead of the product gallery's 2:3 sizes — a chip is
     * tiny and round on the storefront, not a product photo.
     *
     * Parent resets $model->mediaCollections, so call it FIRST then append.
     *
     * The swatch conversions use a distinct name (`swatch`, not `thumb`) and
     * performOnCollections('swatch') so they never clash with the gallery's own
     * thumb/large sizes and never run on gallery images.
     */
    public function registerMediaCollections(HasMedia $model): void
    {
        parent::registerMediaCollections($model);

        $size = self::SWATCH_SIZE;

        $model->addMediaCollection(self::SWATCH_COLLECTION)
            ->registerMediaConversions(function (?Media $media) use ($model, $size) {
                $model->addMediaConversion(self::SWATCH_CONVERSION)
                    ->fit(Fit::Crop, $size, $size)
                    ->keepOriginalImageFormat()
                    ->performOnCollections(self::SWATCH_COLLECTION);

                $model->addMediaConversion(self::SWATCH_CONVERSION.'-webp')
                    ->fit(Fit::Crop, $size, $size)
                    ->format('webp')
                    ->performOnCollections(self::SWATCH_COLLECTION);
            });
    }

    protected function registerCollectionConversions(MediaCollection $collection, HasMedia $model): void
    {
        // Admin-configurable sizes (medium/large/zoom) from settings.
        $sizes = app(MediaSettings::class)->sizes();
        $standard = ['zoom', 'large', 'medium'];

        // Storefront extras derived from the configured sizes (keep the same
        // aspect ratio instead of hard-coding): thumb ~ small, webp ~ large.
        $extra = [
            'thumb' => ['width' => $sizes['small']['width'], 'height' => $sizes['small']['height']],
            'webp' => ['width' => $sizes['large']['width'], 'height' => $sizes['large']['height'], 'format' => 'webp'],
        ];

        // Fit::Crop scales the source to cover the target box and crops to the
        // exact aspect ratio. A source smaller than the target is scaled up to
        // fill the frame rather than padded with a white canvas, so we never
        // get a small image floating in a large white background.
        // Constrain the gallery sizes to the images collection (production always
        // uses config('lunar.media.collection') = 'images') so they don't also
        // run on swatch chips, which only need their small square sizes.
        $imagesCollection = config('lunar.media.collection', 'images');

        $collection->registerMediaConversions(function (?Media $media) use ($model, $sizes, $standard, $extra, $imagesCollection) {
            foreach ($standard as $key) {
                $model->addMediaConversion($key)
                    ->fit(Fit::Crop, $sizes[$key]['width'], $sizes[$key]['height'])
                    ->keepOriginalImageFormat()
                    ->performOnCollections($imagesCollection);
            }

            foreach ($extra as $key => $conv) {
                $conversion = $model->addMediaConversion($key)
                    ->fit(Fit::Crop, $conv['width'], $conv['height'])
                    ->performOnCollections($imagesCollection);

                ($conv['format'] ?? null) === 'webp'
                    ? $conversion->format('webp')
                    : $conversion->keepOriginalImageFormat();
            }
        });
    }
}
