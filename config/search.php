<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Search Driver
    |--------------------------------------------------------------------------
    |
    | Which SearchEngine implementation to use. Storefront/API only talk to the
    | SearchEngine contract, so switching driver requires no caller changes.
    |
    | Supported: "database" (P1, no server) → later "scout" (Meilisearch/Typesense).
    |
    */
    'driver' => env('SEARCH_DRIVER', 'database'),

    /*
    | Built-in drivers (class strings). Additional drivers are registered at
    | runtime via SearchManager::extend(...) — e.g. the `scout` driver is shipped
    | by the acme/scout-search plugin, not hardcoded here.
    */
    'drivers' => [
        'database' => \Modules\Search\Drivers\DatabaseSearchEngine::class,
    ],
];
