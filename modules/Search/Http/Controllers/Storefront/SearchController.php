<?php

namespace Modules\Search\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Data\SearchQuery;
use Modules\Search\Http\Resources\SearchResultResource;

class SearchController extends Controller
{
    public function __construct(
        protected SearchEngine $search,
    ) {}

    /**
     * Search results page (SSR-first). The grid is rendered server-side AND
     * embedded as the same JSON Resource shape the island hydrates from — one
     * contract, no fetch on mount, crawlable results.
     */
    public function __invoke(Request $request): View
    {
        $query = (string) $request->string('q', '');

        $result = $this->search->search(SearchQuery::fromRequest($request));
        $result->items->loadMissing(['variants', 'thumbnail', 'brand']);

        // Same contract as GET /api/v1/search — one shape for SSR + island.
        $state = SearchResultResource::toState($result, $request);

        return view('theme::pages.search', [
            'query' => $query,
            'products' => $result->items,
            'state' => $state,
        ]);
    }
}
