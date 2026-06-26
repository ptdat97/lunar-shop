<?php

namespace Modules\Search\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Data\SearchQuery;
use Modules\Search\Http\Concerns\BroadcastsSearch;
use Modules\Search\Http\Resources\SearchResultResource;

class SearchController extends Controller
{
    use BroadcastsSearch;

    public function __construct(
        protected SearchEngine $search,
    ) {}

    /**
     * GET /api/v1/search?q=...&filters[...]=...
     */
    public function index(Request $request)
    {
        $query = SearchQuery::fromRequest($request);
        $result = $this->search->search($query);

        $result->items->loadMissing(['variants', 'thumbnail', 'brand']);

        $this->broadcastSearch($query, $result);

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
