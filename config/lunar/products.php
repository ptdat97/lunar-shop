<?php

use Lunar\Base\Enums\ProductAssociation;

return [
    'association_types_enum' => ProductAssociation::class,

    /*
    |--------------------------------------------------------------------------
    | Global product options
    |--------------------------------------------------------------------------
    | Handles of the two shared options the simplified variant builder (and
    | storefront pickers) are built around.
    */
    'options' => [
        'size_handle' => 'size',
        'color_handle' => 'color',
    ],
];
