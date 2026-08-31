<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Resources\CollectionResource;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\CollectionService;
use Modules\Core\Support\ApiPagination;
use Symfony\Component\HttpFoundation\Response;

class CollectionController extends Controller
{
    public function __construct(
        protected CollectionService $collections,
    ) {}

    /**
     * GET /api/v1/collections/{slug}
     */
    public function show(Request $request, string $slug): CollectionResource|RedirectResponse
    {
        $collection = $this->collections->findBySlug($slug);

        // Legacy/alias slug → 301 to the canonical collection URL.
        if ($collection === null) {
            $canonical = $this->collections->canonicalSlugFor($slug);

            if ($canonical !== null) {
                return redirect()->route(
                    'api.v1.collections.show',
                    ['slug' => $canonical] + $request->query->all(),
                    Response::HTTP_MOVED_PERMANENTLY,
                );
            }

            abort(404);
        }

        $products = $this->collections->products(
            $collection,
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(60, max(1, (int) $request->input('per_page', 24))),
            sort: $request->input('sort'),
        );

        return (new CollectionResource($collection))
            ->additional([
                'products' => ProductResource::collection($products),
                'meta' => ApiPagination::meta($products),
            ]);
    }
}
