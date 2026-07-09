<?php

namespace Modules\Checkout\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Lunar\Models\Cart;
use Modules\Checkout\Http\Resources\CartResource;
use Modules\Checkout\Services\CartService;
use Modules\Promotion\Http\Resources\CouponResource;
use Modules\Promotion\Services\PromotionService;

/**
 * Coupon endpoints for the session cart (apply/remove return the cart in the
 * same CartResource contract as CartController).
 */
class CouponController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected PromotionService $promotions,
    ) {}

    /**
     * POST /api/v1/cart/coupon  { code }
     */
    public function apply(Request $request): CartResource
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:255']]);

        return $this->resource($this->cart->applyCoupon($data['code']));
    }

    /**
     * DELETE /api/v1/cart/coupon
     */
    public function remove(): CartResource
    {
        return $this->resource($this->cart->removeCoupon());
    }

    /**
     * GET /api/v1/cart/coupons — active coupons the shopper can apply.
     */
    public function available(): AnonymousResourceCollection
    {
        return CouponResource::collection($this->promotions->availableCoupons());
    }

    /**
     * POST /api/v1/cart/coupon/validate  { code } — check a code without
     * applying it (live storefront feedback). Returns 200 with {valid, ...}.
     */
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:255']]);

        return response()->json($this->promotions->validateCoupon($data['code']));
    }

    /**
     * Wrap a cart in the stable JSON contract, with the relations
     * CartResource renders — same hydration as CartController.
     */
    protected function resource(Cart $cart): CartResource
    {
        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }
}
