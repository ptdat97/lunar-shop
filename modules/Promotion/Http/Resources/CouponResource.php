<?php

namespace Modules\Promotion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lunar\Models\Discount;
use Modules\Promotion\Services\PromotionService;

/**
 * Stable JSON contract for an applicable coupon (cart "available coupons" list).
 *
 * @mixin Discount
 */
class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->coupon,
            'name' => $this->name,
            'description' => app(PromotionService::class)->describe($this->resource),
        ];
    }
}
