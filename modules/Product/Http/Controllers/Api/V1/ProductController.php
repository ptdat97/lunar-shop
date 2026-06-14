<?php

namespace Modules\Product\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Product\Http\Resources\ProductResource;
use Modules\Product\Services\ProductService;
use Modules\Search\Data\SearchQuery;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $products,
    ) {}

    /**
     * GET /api/v1/products
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $result = $this->products->list(SearchQuery::fromRequest($request));

        $result->items->loadMissing(['variants', 'thumbnail', 'brand']);

        return ProductResource::collection($result->items)
            ->additional([
                'meta' => [
                    'total' => $result->total,
                    'page' => $result->page,
                    'per_page' => $result->perPage,
                    'last_page' => $result->lastPage(),
                ],
            ]);
    }

    /**
     * GET /api/v1/products/{slug}
     */
    public function show(string $slug): ProductResource
    {
        $product = $this->products->findBySlug($slug);

        abort_if($product === null, 404);

        return new ProductResource($product);
    }
}
