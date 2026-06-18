<?php

namespace Modules\Search\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Product\Http\Resources\ProductResource;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Data\SearchQuery;

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

        $state = ProductResource::collection($result->items)
            ->additional([
                'facets' => $result->facets,
                'meta' => [
                    'total' => $result->total,
                    'page' => $result->page,
                    'per_page' => $result->perPage,
                    'last_page' => $result->lastPage(),
                ],
            ])
            ->response($request)
            ->getData(true);

        return view('theme::pages.search', [
            'query' => $query,
            'products' => $result->items,
            'state' => $state,
        ]);
    }
}
