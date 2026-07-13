<?php

namespace Lunar\Base;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media definitions for ProductOptionValue: on top of the standard 'images'
 * collection, option values carry a single 'swatch' image (e.g. a fabric
 * close-up for a colour) that storefront option pickers render instead of a
 * flat hex colour. See ProductOptionValue::swatchImageUrl().
 */
class OptionValueMediaDefinitions extends StandardMediaDefinitions
{
    public const SWATCH_COLLECTION = 'swatch';

    public function registerMediaCollections(HasMedia $model): void
    {
        parent::registerMediaCollections($model);

        $collection = $model
            ->addMediaCollection(self::SWATCH_COLLECTION)
            ->singleFile();

        $collection->registerMediaConversions(function (Media $media) use ($model) {
            $model->addMediaConversion('thumb')
                ->fit(Fit::Crop, 120, 120)
                ->keepOriginalImageFormat();
        });
    }

    public function getMediaCollectionTitles(): array
    {
        return parent::getMediaCollectionTitles() + [
            self::SWATCH_COLLECTION => __('lunar::base.standard-media-definitions.collection-titles.swatch'),
        ];
    }

    public function getMediaCollectionDescriptions(): array
    {
        return parent::getMediaCollectionDescriptions() + [
            self::SWATCH_COLLECTION => '',
        ];
    }
}
