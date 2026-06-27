<?php

namespace Modules\Search\Contracts;

use Modules\Search\Data\SearchQuery;
use Modules\Search\Data\SearchResult;

/**
 * Engine-agnostic search contract.
 *
 * Controllers talk only to this interface, never to a concrete engine. Swapping
 * the database driver for Scout/Meilisearch later means writing one driver and
 * re-binding SearchEngine in SearchServiceProvider — no caller change.
 */
interface SearchEngine
{
    /**
     * Run a search and return a normalised result (items + facets + pagination).
     */
    public function search(SearchQuery $query): SearchResult;

    /**
     * Autocomplete suggestions for a term.
     *
     * @return array<int, string>
     */
    public function suggest(string $term, int $limit = 10): array;
}
