<?php

namespace Modules\Media\Definitions;

use Lunar\Base\StandardMediaDefinitions;
use Modules\Media\Services\MediaSettings;
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
    public function registerMediaConversions(HasMedia $model, ?Media $media = null): void
    {
        $small = app(MediaSettings::class)->sizes()['small'];

        $model->addMediaConversion('small')
            ->fit(Fit::Crop, $small['width'], $small['height'])
            ->sharpen(10)
            ->keepOriginalImageFormat();
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
        $collection->registerMediaConversions(function (?Media $media) use ($model, $sizes, $standard, $extra) {
            foreach ($standard as $key) {
                $model->addMediaConversion($key)
                    ->fit(Fit::Crop, $sizes[$key]['width'], $sizes[$key]['height'])
                    ->keepOriginalImageFormat();
            }

            foreach ($extra as $key => $conv) {
                $conversion = $model->addMediaConversion($key)
                    ->fit(Fit::Crop, $conv['width'], $conv['height']);

                ($conv['format'] ?? null) === 'webp'
                    ? $conversion->format('webp')
                    : $conversion->keepOriginalImageFormat();
            }
        });
    }
}
