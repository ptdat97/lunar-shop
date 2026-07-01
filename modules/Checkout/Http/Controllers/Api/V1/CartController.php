<?php

namespace Modules\Checkout\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Checkout\Http\Resources\CartResource;
use Modules\Checkout\Services\CartService;
use Modules\Promotion\Http\Resources\CouponResource;
use Modules\Promotion\Services\PromotionService;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected PromotionService $promotions,
    ) {}

    /**
     * GET /api/v1/cart
     */
    public function show(): CartResource
    {
        return new CartResource($this->cart->current()->loadMissing('lines.purchasable.product'));
    }

    /**
     * POST /api/v1/cart  { variant_id, quantity }
     */
    public function store(Request $request): CartResource
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cart->add($data['variant_id'], $data['quantity'] ?? 1);

        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }

    /**
     * PATCH /api/v1/cart/lines/{line}  { quantity }
     */
    public function updateLine(Request $request, int $line): CartResource
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cart->updateLine($line, $data['quantity']);

        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }

    /**
     * DELETE /api/v1/cart/lines/{line}
     */
    public function destroyLine(int $line): CartResource
    {
        $cart = $this->cart->remove($line);

        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }

    /**
     * POST /api/v1/cart/coupon  { code }
     */
    public function applyCoupon(Request $request): CartResource
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:255']]);

        $cart = $this->cart->applyCoupon($data['code']);

        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }

    /**
     * DELETE /api/v1/cart/coupon
     */
    public function removeCoupon(): CartResource
    {
        $cart = $this->cart->removeCoupon();

        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }

    /**
     * GET /api/v1/cart/coupons — active coupons the shopper can apply.
     */
    public function availableCoupons(): AnonymousResourceCollection
    {
        return CouponResource::collection($this->promotions->availableCoupons());
    }

    /**
     * POST /api/v1/cart/coupon/validate  { code } — check a code without
     * applying it (live storefront feedback). Returns 200 with {valid, ...}.
     */
    public function validateCoupon(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:255']]);

        return response()->json($this->promotions->validateCoupon($data['code']));
    }
}
