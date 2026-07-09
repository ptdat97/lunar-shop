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
    // On-demand conversion behaviour (read by MediaUrl / ConversionGenerator).
    'on_demand' => [
        // When false (default), a page render never generates images: it emits
        // the exact conversion URL and the file is produced by the pre-warm job
        // on the `media` queue OR — when Horizon isn't running — by the
        // browser's own image request via the media.conversion fallback route
        // (one size per request, in parallel). When true, the first request for
        // a missing conversion generates it inline during the render (serial —
        // a page with many missing sizes renders slowly).
        'sync' => (bool) env('MEDIA_ON_DEMAND_SYNC', false),
    ],

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
