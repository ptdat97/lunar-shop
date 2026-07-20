<?php

use Lunar\Base\StandardMediaDefinitions;
use Modules\Assets\Definitions\FashionMediaDefinitions;

return [

    'definitions' => [
        'asset' => StandardMediaDefinitions::class,
        'brand' => StandardMediaDefinitions::class,
        'collection' => StandardMediaDefinitions::class,
        'product' => StandardMediaDefinitions::class,
        'product-option' => StandardMediaDefinitions::class,
        // NB: lookups snake_case the model basename, so this key (not the
        // kebab-case one above) is the one that actually matches. Option values
        // carry the `swatch` collection defined by FashionMediaDefinitions.
        'product_option_value' => FashionMediaDefinitions::class,
    ],

    'collection' => 'images',

    'fallback' => [
        'url' => env('FALLBACK_IMAGE_URL', null),
        'path' => env('FALLBACK_IMAGE_PATH', null),
    ],

];
