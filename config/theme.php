<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active Theme
    |--------------------------------------------------------------------------
    |
    | The storefront theme to render. Themes live in themes/<slug> and contain
    | only views/js/css (no business logic). Data is supplied by module
    | services / the /api/v1 layer. Switching brands = point this at another
    | theme directory.
    |
    */
    'active' => env('THEME', 'fashion'),

    /*
    | Base path where themes are stored (relative to the project root).
    */
    'path' => 'themes',
];
