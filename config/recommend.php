<?php

use Modules\Catalog\Strategies\AssociationStrategy;
use Modules\Catalog\Strategies\CollectionStrategy;
use Modules\Catalog\Strategies\CoPurchaseStrategy;

return [
    /*
    | Strategy priority order (first = highest). The service runs them in this
    | order, de-dupes, and stops once it has enough. Add CoPurchase/AlsoViewed
    | strategies here in P2/P3 without touching callers.
    */
    'strategies' => [
        // Curated (hand-picked) first, then automatic co-purchase history, then
        // the collection-similarity fallback fills any remaining slots.
        AssociationStrategy::class,
        CoPurchaseStrategy::class,
        CollectionStrategy::class,
    ],

    // Cache TTL (seconds) for product-page recommendations. Cart is never cached.
    'cache_ttl' => env('RECOMMEND_CACHE_TTL', 3600),

    // Default limits.
    'product_limit' => 8,
    'cart_limit' => 6,
];
