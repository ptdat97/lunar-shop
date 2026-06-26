<?php

namespace Modules\Search\Http\Concerns;

use Modules\Platform\Facades\Hook;
use Modules\Platform\Support\Hooks;
use Modules\Search\Data\SearchQuery;
use Modules\Search\Data\SearchResult;

/**
 * Fires the `search.performed` action for keyword searches (not blank browse).
 * Shared by the storefront + API search controllers so both paths emit one
 * consistent event for analytics / "no results" reporting.
 */
trait BroadcastsSearch
{
    protected function broadcastSearch(SearchQuery $query, SearchResult $result): void
    {
        $term = trim($query->term);

        if ($term === '') {
            return;
        }

        Hook::doAction(Hooks::SEARCH_PERFORMED, [$term, $result->total]);
    }
}
