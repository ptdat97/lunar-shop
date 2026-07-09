<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Lunar\Models\Product;
use Modules\Catalog\Http\Requests\SizeRecommendRequest;
use Modules\Catalog\Services\FitHistoryService;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\SizeChartService;
use Modules\Catalog\Services\SizeRecommender;
use Modules\Customer\Services\CustomerResolver;

/**
 * Fashion Size Intelligence API: size chart + size recommendation for a product.
 */
class SizeController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected SizeChartService $chart,
        protected SizeRecommender $recommender,
        protected FitHistoryService $fitHistory,
        protected CustomerResolver $customers,
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

        $data = $this->recommender->recommend($product, $request->measurements());

        // Additive: what the shopper kept vs returned before. Absent for guests
        // and for anyone without size history, so the measurement result stands
        // on its own.
        $data['fit_history'] = $this->fitHistoryFor($request, $product);

        return response()->json(['data' => $data]);
    }

    /**
     * Fit signal for the authenticated shopper, or null when signed out /
     * no customer record / nothing learned yet.
     *
     * This endpoint is public (guests get a measurement-only result and must
     * never see a 401), so the user is resolved rather than required. The
     * sanctum guard covers both the SPA cookie session and a bearer token.
     */
    protected function fitHistoryFor(Request $request, Product $product): ?array
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return null;
        }

        $customer = $this->customers->existingForUser($user);

        return $customer ? $this->fitHistory->for($customer, $product) : null;
    }
}
