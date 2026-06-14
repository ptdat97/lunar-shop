<?php

namespace Modules\Search\Http\Controllers\Api\V1;

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
     * GET /api/v1/search?q=...&filters[...]=...
     */
    public function index(Request $request)
    {
        $result = $this->search->search(SearchQuery::fromRequest($request));

        $result->items->loadMissing(['variants', 'thumbnail', 'brand']);

        return ProductResource::collection($result->items)
            ->additional([
                'facets' => $result->facets,
                'meta' => [
                    'total' => $result->total,
                    'page' => $result->page,
                    'per_page' => $result->perPage,
                    'last_page' => $result->lastPage(),
                ],
            ]);
    }

    /**
     * GET /api/v1/search/suggest?q=...
     */
    public function suggest(Request $request)
    {
        return response()->json([
            'data' => $this->search->suggest(
                (string) $request->string('q', ''),
                min(20, max(1, (int) $request->input('limit', 10))),
            ),
        ]);
    }
}
