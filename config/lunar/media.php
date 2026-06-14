<?php

use Lunar\Base\StandardMediaDefinitions;
use Modules\Media\Definitions\FashionMediaDefinitions;

return [

    'definitions' => [
        'asset' => StandardMediaDefinitions::class,
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
