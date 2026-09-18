<?php

namespace Modules\Checkout\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Checkout\Http\Resources\CartResource;
use Modules\Checkout\Services\CartService;
use Modules\Promotion\Services\LoyaltyService;

/**
 * Tiêu điểm cho giỏ hiện tại.
 *
 * Nằm ở Checkout chứ không phải Promotion, cùng chỗ với `CouponController`: cả
 * hai thao tác trên giỏ trong session và trả về cùng hợp đồng `CartResource`.
 * Luật điểm thì vẫn ở `LoyaltyService` — controller không quyết định gì.
 */
class LoyaltyController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected LoyaltyService $loyalty,
    ) {}

    /**
     * POST /api/v1/cart/loyalty  { points }
     *
     * Lỗi trả về theo TRƯỜNG (`points`) chứ không phải một câu chung, để form
     * checkout chỉ được đúng ô hỏng — cùng cách áp mã giảm giá đang làm.
     */
    public function store(Request $request): CartResource
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:0'],
        ]);

        $cart = $this->cart->current();
        $points = (int) $data['points'];

        if ($points > 0) {
            $this->guard($cart, $points);
        }

        return CartResource::make($this->loyalty->redeem($cart, $points));
    }

    /** DELETE /api/v1/cart/loyalty */
    public function destroy(): CartResource
    {
        return CartResource::make($this->loyalty->redeem($this->cart->current(), 0));
    }

    /**
     * Ba lý do từ chối, mỗi lý do một câu khách sửa được.
     *
     * Kẹp im lặng ({@see LoyaltyService::redemptionFor()}) là đúng cho việc TÍNH
     * lại giỏ, nhưng sai cho lượt bấm này: khách gõ 500 điểm rồi thấy trừ 200 mà
     * không ai nói gì là tệ hơn một lời từ chối.
     */
    protected function guard($cart, int $points): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['points' => $message]);

        if (! $this->loyalty->enabled() || ! $cart->customer) {
            $fail(__('storefront.loyalty.unavailable'));
        }

        if ($points < $this->loyalty->minRedeem()) {
            $fail(__('storefront.loyalty.below_minimum', ['min' => $this->loyalty->minRedeem()]));
        }

        $max = $this->loyalty->maxRedeemableFor($cart);

        if ($points > $max) {
            $fail(__('storefront.loyalty.too_many', ['max' => $max]));
        }
    }
}
