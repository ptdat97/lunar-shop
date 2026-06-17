<?php

use Lunar\Base\StandardMediaDefinitions;
use Modules\Media\Definitions\FashionMediaDefinitions;

return [

    'definitions' => [
        // Media Library assets get the same responsive + WebP conversions as
        // storefront imagery so picked files are ready for the front end.
        'asset' => FashionMediaDefinitions::class,
        'brand' => StandardMediaDefinitions::class,
        // Storefront-facing models get responsive + WebP conversions.
        'collection' => FashionMediaDefinitions::class,
        'product' => FashionMediaDefinitions::class,
        'product-option' => StandardMediaDefinitions::class,
        'product-option-value' => StandardMediaDefinitions::class,
    ],

    'collection' => 'images',

    'fallback' => [
        'url' => env('FALLBACK_IMAGE_URL', null),
        'path' => env('FALLBACK_IMAGE_PATH', null),
    ],

];
