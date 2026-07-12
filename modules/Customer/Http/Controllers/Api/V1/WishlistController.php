<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Services\WishlistService;
use Modules\Catalog\Http\Resources\ProductResource;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlist,
    ) {}

    /**
     * GET /api/v1/wishlist — the user's wishlist products. Public on purpose:
     * the storefront loads this on every page to mark hearts, so a guest must
     * get an empty list (200) rather than a 401 error in the console.
     *
     * Resolved through the **sanctum** guard: this route lives in the `web`
     * group, whose default guard is session-backed and blind to a bearer token,
     * so a signed-in app/headless client would otherwise be handed an empty
     * wishlist. `sanctum` reads both a cookie session and a bearer token, and
     * still returns null for a genuine guest.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user) {
            return response()->json(['data' => [], 'product_ids' => []]);
        }

        return response()->json([
            'data' => ProductResource::collection($this->wishlist->productsFor($user)),
            'product_ids' => $this->wishlist->productIdsFor($user)->values(),
        ]);
    }

    /**
     * POST /api/v1/wishlist  { product_id } — toggle membership.
     */
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        return response()->json([
            'data' => $this->wishlist->toggle($request->user(), $data['product_id']),
        ]);
    }
}
