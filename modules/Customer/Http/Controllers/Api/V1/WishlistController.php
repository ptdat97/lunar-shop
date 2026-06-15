<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Models\Product;
use Modules\Customer\Models\WishlistItem;
use Modules\Product\Http\Resources\ProductResource;

class WishlistController extends Controller
{
    /**
     * GET /api/v1/wishlist — the user's wishlist products.
     */
    public function index(Request $request): JsonResponse
    {
        $productIds = $this->items($request)->pluck('product_id');

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with(['variants', 'thumbnail', 'brand'])
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products),
            'product_ids' => $productIds->values(),
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

        $existing = WishlistItem::where('user_id', $request->user()->id)
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            WishlistItem::create([
                'user_id' => $request->user()->id,
                'product_id' => $data['product_id'],
            ]);
            $inWishlist = true;
        }

        return response()->json([
            'data' => [
                'in_wishlist' => $inWishlist,
                'count' => $this->items($request)->count(),
            ],
        ]);
    }

    protected function items(Request $request)
    {
        return WishlistItem::where('user_id', $request->user()->id);
    }
}
