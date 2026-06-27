<?php

namespace Modules\Search\Drivers;

use Lunar\Models\Product;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Data\SearchQuery;
use Modules\Search\Data\SearchResult;

/**
 * Scout-backed engine. Wraps Lunar's Searchable Product (which already ships a
 * ScoutIndexer) — we inherit indexing/mapping and only adapt the result shape
 * to the SearchResult contract. Works with any Scout driver (database,
 * Meilisearch, Typesense, Algolia) via SCOUT_DRIVER.
 */
class ScoutSearchEngine implements SearchEngine
{
    public function search(SearchQuery $query): SearchResult
    {
        $builder = Product::search($query->term);

        // Only published products on the storefront.
        $builder->where('status', 'published');

        $paginator = $builder->paginate($query->perPage, 'page', $query->page);

        return new SearchResult(
            items: $paginator->getCollection(),
            total: $paginator->total(),
            page: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            facets: [], // engine-specific facets can be mapped here later
        );
    }

    public function suggest(string $term, int $limit = 10): array
    {
        if (trim($term) === '') {
            return [];
        }

        return Product::search($term)
            ->where('status', 'published')
            ->take($limit)
            ->get()
            ->map(fn (Product $p) => (string) $p->translateAttribute('name'))
            ->filter()
            ->values()
            ->all();
    }
}
