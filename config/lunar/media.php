<?php

use Lunar\Core\Media\StandardDefinitions;

return [

    'definitions' => [
        'asset' => StandardDefinitions::class,
        'brand' => StandardDefinitions::class,
        'collection' => StandardDefinitions::class,
        'product' => StandardDefinitions::class,
        'product-option' => StandardDefinitions::class,
        'product-option-value' => StandardDefinitions::class,
        // `mergeConfigFrom` is SHALLOW, and that cuts two ways:
        //
        //  - a TOP-LEVEL key we omit is filled in from the package, so it keeps
        //    tracking upstream (`max_upload_kb` is deliberately absent here);
        //  - a key NESTED under one we declare — like this list — is never
        //    merged at all, so omitting it silently pins the old shape.
        //
        // `product_type` arrived in 2.0 and maps to StandardDefinitions, which
        // is also what the lookup falls back to, so its absence changed nothing.
        // It is listed anyway: the day upstream points it somewhere else we want
        // the change, not the silent fallback.
        'product_type' => StandardDefinitions::class,
    ],

    'collection' => 'images',

    'fallback' => [
        'url' => env('FALLBACK_IMAGE_URL', null),
        'path' => env('FALLBACK_IMAGE_PATH', null),
    ],

];
