<?php

namespace Modules\Media\Definitions;

use Lunar\Base\StandardMediaDefinitions;
use Modules\Media\Services\MediaSettings;
use Spatie\Image\Enums\BorderType;
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
     */
    public function registerMediaConversions(HasMedia $model, ?Media $media = null): void
    {
        $small = app(MediaSettings::class)->sizes()['small'];

        $model->addMediaConversion('small')
            ->fit(Fit::Fill, $small['width'], $small['height'])
            ->border(0, BorderType::Overlay, color: '#FFF')
            ->background('#FFF')
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

        $collection->registerMediaConversions(function (?Media $media) use ($model, $sizes, $standard, $extra) {
            foreach ($standard as $key) {
                $model->addMediaConversion($key)
                    ->fit(Fit::Fill, $sizes[$key]['width'], $sizes[$key]['height'])
                    ->border(0, BorderType::Overlay, color: '#FFF')
                    ->background('#FFF')
                    ->keepOriginalImageFormat();
            }

            foreach ($extra as $key => $conv) {
                $conversion = $model->addMediaConversion($key)
                    ->fit(Fit::Fill, $conv['width'], $conv['height']);

                ($conv['format'] ?? null) === 'webp'
                    ? $conversion->format('webp')
                    : $conversion->keepOriginalImageFormat();
            }
        });
    }
}
