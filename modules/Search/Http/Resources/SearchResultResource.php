<?php

namespace Modules\Search\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Product\Http\Resources\ProductResource;
use Modules\Search\Data\SearchResult;

/**
 * Canonical JSON contract for a paginated, faceted listing.
 *
 * One shape — `{ data, facets, meta }` — shared by GET /api/v1/search, the
 * collection page and the search page SSR hydration payload, so server-rendered
 * HTML and the island state can never drift. Items serialise through
 * {@see ProductResource}; facets + pagination come from the {@see SearchResult}.
 */
class SearchResultResource
{
    /**
     * Build the product collection with facets + meta attached, exactly as
     * returned by the API. Used directly as an API response and as the source
     * for the SSR hydration payload (see {@see self::toState()}).
     */
    public static function collection(SearchResult $result): AnonymousResourceCollection
    {
        $additional = [
            'facets' => $result->facets,
            'meta' => [
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->perPage,
                'last_page' => $result->lastPage(),
            ],
        ];

        return ProductResource::collection($result->items)->additional($additional);
    }

    /**
     * The same contract as a plain array, for embedding as the SSR hydration
     * payload (`@json($state)`) without issuing an HTTP response.
     *
     * @return array<string, mixed>
     */
    public static function toState(SearchResult $result, Request $request): array
    {
        return self::collection($result)->response($request)->getData(true);
    }
}
