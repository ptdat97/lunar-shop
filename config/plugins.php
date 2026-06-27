<?php

return [
    /*
    | The app's own version, used to satisfy a plugin's `requires.core`
    | constraint (semver). Bump this when the hook/extension surface changes in a
    | breaking way so incompatible plugins are skipped instead of half-loading.
    */
    'core_version' => '1.0.0',

    /*
    | Allow-list of plugin ids that may load. A plugin is discovered (its
    | manifest read) but only registered if its id is listed here — so dropping a
    | package in never runs its code until you opt in. Empty = no plugins load.
    */
    'enabled' => [
        // First-party storefront features extracted from modules (Phase 4) —
        // enabled by default so they work out of the box.
        'acme/wishlist',
        'acme/recommend',
        'acme/analytics',
        'acme/workflow',

        // Optional plugins: enable per-deploy, then `php artisan plugin:install <id>`:
        // 'acme/reviews',
        // 'acme/preorder',
        // 'acme/scout-search',
    ],

    /*
    | Directories scanned for path-based plugins. Each immediate `<vendor>/<name>`
    | subfolder with a `plugin.json` is a candidate. Composer packages that
    | declare `extra.lunar-sme.plugin` are discovered automatically and don't
    | need to live here.
    */
    'paths' => [
        base_path('plugins'),
    ],
];
