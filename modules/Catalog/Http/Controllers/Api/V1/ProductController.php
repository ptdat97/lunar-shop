<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\SizeChartService;
use Modules\Catalog\Services\RecommendationService;
use Modules\Catalog\Data\SearchQuery;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected SizeChartService $sizeChart,
        protected RecommendationService $recommend,
    ) {}

    /**
     * GET /api/v1/products
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // `slugs` (CSV or array) → ordered lookup for "recently viewed". Returns
        // a plain ordered collection (no pagination) via the same ProductResource.
        if ($request->filled('slugs')) {
            $slugs = is_array($request->input('slugs'))
                ? $request->input('slugs')
                : explode(',', (string) $request->input('slugs'));

            return ProductResource::collection($this->products->bySlugs($slugs));
        }

        // The search engine already eager-loads variants, thumbnail, brand and
        // media (used by ProductResource.hover_thumbnail), so no loadMissing here.
        $result = $this->products->list(SearchQuery::fromRequest($request));

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
     *
     * Optional `?include=size_chart,related` attaches the same extras the web
     * product page renders (R3 — web↔API parity for headless clients). Without
     * `include` the shape is unchanged.
     */
    public function show(Request $request, string $slug): ProductResource
    {
        $product = $this->products->findBySlug($slug);

        abort_if($product === null, 404);

        $include = array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('include', ''))
        ));

        $resource = new ProductResource($product);

        if (in_array('size_chart', $include, true)) {
            $resource->withSizeChart($this->sizeChart->for($product));
        }

        if (in_array('related', $include, true)) {
            $resource->withRelated($this->recommend->forProduct($product));
        }

        return $resource;
    }
}
