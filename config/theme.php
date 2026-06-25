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

    /*
    |--------------------------------------------------------------------------
    | Storefront locales
    |--------------------------------------------------------------------------
    |
    | Languages offered in the storefront language switcher. Keys are locale
    | codes (must match a lang/<code> dir + ideally Lunar `lunar_languages`);
    | values are the labels shown in the switcher. The first entry is the
    | default when none is chosen / an unknown locale is requested.
    |
    */
    'locales' => [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ],
];
