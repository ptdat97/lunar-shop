<?php

namespace Modules\Product\Services;

use Lunar\Models\Product;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Data\SearchQuery;
use Modules\Search\Data\SearchResult;

/**
 * Single source of product read-logic. Both the Storefront controller and the
 * API controller call this — no duplicated business logic.
 */
class ProductService
{
    public function __construct(
        protected SearchEngine $search,
    ) {}

    /**
     * Paginated/filtered product listing (delegates to the search abstraction).
     */
    public function list(SearchQuery $query): SearchResult
    {
        return $this->search->search($query);
    }

    /**
     * Resolve a single published product by its URL slug.
     */
    public function findBySlug(string $slug): ?Product
    {
        return Product::query()
            ->where('status', 'published')
            ->whereHas('urls', fn ($u) => $u->where('slug', $slug))
            ->with(['variants', 'thumbnail', 'brand', 'collections'])
            ->first();
    }
}
