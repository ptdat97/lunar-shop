<?php

namespace Modules\Catalog\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Modules\Catalog\Http\Resources\SearchResultResource;

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
        // `media` powers the product-card hover (second) image (N+1-free grid).
        $result->items->loadMissing(['variants', 'thumbnail', 'brand', 'media']);

        // Same contract as GET /api/v1/search — one shape for SSR + island.
        $state = SearchResultResource::toState($result, $request);

        return view('theme::pages.search', [
            'query' => $query,
            'products' => $result->items,
            'state' => $state,
        ]);
    }
}
