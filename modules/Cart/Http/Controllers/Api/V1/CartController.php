<?php

namespace Modules\Cart\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Models\Discount;
use Modules\Cart\Http\Resources\CartResource;
use Modules\Cart\Services\CartService;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cart,
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
    public function availableCoupons(): JsonResponse
    {
        $coupons = Discount::query()
            ->whereNotNull('coupon')
            ->active()
            ->usable()
            ->orderByDesc('priority')
            ->get(['coupon', 'name'])
            ->map(fn (Discount $d) => [
                'code' => $d->coupon,
                'name' => $d->name,
            ]);

        return response()->json(['data' => $coupons]);
    }
}
