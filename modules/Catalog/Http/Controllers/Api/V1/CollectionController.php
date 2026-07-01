<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Resources\CollectionResource;
use Modules\Catalog\Services\CollectionService;
use Modules\Catalog\Http\Resources\ProductResource;

class CollectionController extends Controller
{
    public function __construct(
        protected CollectionService $collections,
    ) {}

    /**
     * GET /api/v1/collections/{slug}
     */
    public function show(Request $request, string $slug)
    {
        $collection = $this->collections->findBySlug($slug);

        abort_if($collection === null, 404);

        $products = $this->collections->products(
            $collection,
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(60, max(1, (int) $request->input('per_page', 24))),
        );

        return (new CollectionResource($collection))
            ->additional([
                'products' => ProductResource::collection($products),
                'meta' => [
                    'total' => $products->total(),
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'last_page' => $products->lastPage(),
                ],
            ]);
    }
}
