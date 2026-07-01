<?php

use Lunar\Base\StandardMediaDefinitions;
use Modules\Assets\Definitions\FashionMediaDefinitions;

/**
 * Module-local overrides for Lunar's `lunar.media` config.
 *
 * Kept here (not in config/lunar/media.php) so `php artisan vendor:publish
 * --tag=lunar --force` can never wipe them — AssetsServiceProvider re-applies
 * these over the published Lunar defaults at boot. Only list the keys we change.
 */
return [
    'definitions' => [
        // Media Library assets get the same responsive + WebP conversions as
        // storefront imagery so picked files are ready for the front end.
        'asset' => FashionMediaDefinitions::class,
        // Storefront-facing models get responsive + WebP conversions.
        'collection' => FashionMediaDefinitions::class,
        'product' => FashionMediaDefinitions::class,
        // The rest stay on Lunar's StandardMediaDefinitions (not overridden):
        // brand, product-option, product-option-value.
        'brand' => StandardMediaDefinitions::class,
        'product-option' => StandardMediaDefinitions::class,
        'product-option-value' => StandardMediaDefinitions::class,
    ],
];
