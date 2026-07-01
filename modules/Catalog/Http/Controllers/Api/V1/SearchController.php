<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

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
     * GET /api/v1/search?q=...&filters[...]=...
     */
    public function index(Request $request)
    {
        $result = $this->search->search(SearchQuery::fromRequest($request));

        $result->items->loadMissing(['variants', 'thumbnail', 'brand']);

        return SearchResultResource::collection($result);
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
