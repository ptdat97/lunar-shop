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

    'drivers' => [
        'database' => \Modules\Search\Drivers\DatabaseSearchEngine::class,
        'scout' => \Modules\Search\Drivers\ScoutSearchEngine::class,
    ],
];
