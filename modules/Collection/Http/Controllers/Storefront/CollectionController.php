<?php

namespace Modules\Collection\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Collection\Services\CollectionService;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Data\SearchQuery;
use Modules\Search\Http\Resources\SearchResultResource;

class CollectionController extends Controller
{
    public function __construct(
        protected CollectionService $collections,
        protected SearchEngine $search,
    ) {}

    /**
     * Collection page (SSR-first).
     *
     * The product grid is resolved through the SAME SearchEngine the island
     * calls, so server-rendered HTML and the hydrated state never diverge. The
     * controller passes both:
     *   - $products  → Eloquent models for the SSR grid (real HTML, crawlable)
     *   - $state     → the JSON Resource payload ({ data, facets, meta }) embedded
     *                  for the Vue island to hydrate from — no fetch on mount.
     */
    public function show(Request $request, string $slug): View
    {
        $collection = $this->collections->findBySlug($slug);

        abort_if($collection === null, 404);

        // Force the engine scope to this collection regardless of request input.
        $request->merge(['scope' => $collection->defaultUrl?->slug]);

        $result = $this->search->search(SearchQuery::fromRequest($request));
        $result->items->loadMissing(['variants', 'thumbnail', 'brand']);

        // Same contract as GET /api/v1/search — one shape for SSR + island.
        $state = SearchResultResource::toState($result, $request);

        return view('theme::pages.collection', [
            'collection' => $collection,
            'products' => $result->items,   // SSR grid
            'facets' => $result->facets,    // SSR facet sidebar
            'state' => $state,              // hydration payload
            'currentSort' => $request->input('sort', 'best-selling'),
        ]);
    }
}
