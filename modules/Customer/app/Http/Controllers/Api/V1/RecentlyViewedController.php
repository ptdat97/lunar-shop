<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Lunar\Models\Product;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Customer\Services\RecentlyViewedService;

/**
 * Server-side "recently viewed", so the list follows a signed-in shopper from
 * the browser to the app. Guests keep the storefront's localStorage list.
 */
class RecentlyViewedController extends Controller
{
    public function __construct(
        protected RecentlyViewedService $recentlyViewed,
    ) {}

    /**
     * GET /api/v1/customer/recently-viewed
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = min(RecentlyViewedService::MAX, max(1, (int) $request->input('limit', 12)));

        // Same ProductResource the grid and search use — one product shape.
        return ProductResource::collection(
            $this->recentlyViewed->productsFor($request->user(), $limit),
        );
    }

    /**
     * POST /api/v1/customer/recently-viewed  { product_id }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:lunar_products,id'],
        ]);

        // Only published products: an unpublished one would be recorded and then
        // silently dropped on read, which is just a wasted row.
        $published = Product::whereKey($data['product_id'])->where('status', 'published')->exists();

        abort_unless($published, 404);

        $this->recentlyViewed->record($request->user(), $data['product_id']);

        return response()->json(['data' => ['status' => 'recorded']], 201);
    }

    /**
     * DELETE /api/v1/customer/recently-viewed
     */
    public function destroy(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['cleared' => $this->recentlyViewed->clear($request->user())],
        ]);
    }
}
