<?php

namespace Modules\Catalog\Strategies;

use Illuminate\Support\Collection;
use Lunar\Models\Product;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Contracts\RecommendationStrategy;

/**
 * Fallback recommendations: other published products in the same collection(s).
 * Wraps the existing ProductService::related() so we keep one source of truth
 * (not a second copy of the collection-similarity query).
 */
class CollectionStrategy implements RecommendationStrategy
{
    public function __construct(
        protected ProductService $products,
    ) {}

    public function for(Product $product, int $limit = 8): Collection
    {
        return collect($this->products->related($product, $limit))->values();
    }
}
