<?php

namespace Acme\Recommend;

use Illuminate\Support\Collection;
use Lunar\Models\Product;

/**
 * A single way of producing product recommendations for a given product.
 * Strategies are combined (in priority order) by the RecommendationService.
 */
interface RecommendationStrategy
{
    /**
     * Recommended products for the given source product.
     *
     * @return Collection<int, Product>  published products, source excluded
     */
    public function for(Product $product, int $limit = 8): Collection;
}
