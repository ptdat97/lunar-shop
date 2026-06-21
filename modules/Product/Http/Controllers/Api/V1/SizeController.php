<?php

namespace Modules\Product\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Product\Http\Requests\SizeRecommendRequest;
use Modules\Product\Services\ProductService;
use Modules\Product\Services\SizeChartService;
use Modules\Product\Services\SizeRecommender;

/**
 * Fashion Size Intelligence API: size chart + size recommendation for a product.
 */
class SizeController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected SizeChartService $chart,
        protected SizeRecommender $recommender,
    ) {}

    /**
     * GET /api/v1/products/{slug}/size-chart
     */
    public function chart(string $slug): JsonResponse
    {
        $product = $this->products->findBySlug($slug);

        abort_if($product === null, 404);

        return response()->json(['data' => $this->chart->for($product)]);
    }

    /**
     * POST /api/v1/products/{slug}/recommend-size
     */
    public function recommend(SizeRecommendRequest $request, string $slug): JsonResponse
    {
        $product = $this->products->findBySlug($slug);

        abort_if($product === null, 404);

        return response()->json([
            'data' => $this->recommender->recommend($product, $request->measurements()),
        ]);
    }
}
