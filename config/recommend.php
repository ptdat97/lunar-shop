<?php

return [
    /*
    | Strategy priority order (first = highest). The service runs them in this
    | order, de-dupes, and stops once it has enough. Add CoPurchase/AlsoViewed
    | strategies here in P2/P3 without touching callers.
    */
    'strategies' => [
        \Modules\Catalog\Strategies\AssociationStrategy::class,
        \Modules\Catalog\Strategies\CollectionStrategy::class,
    ],

    // Cache TTL (seconds) for product-page recommendations. Cart is never cached.
    'cache_ttl' => env('RECOMMEND_CACHE_TTL', 3600),

    // Default limits.
    'product_limit' => 8,
    'cart_limit' => 6,
];
