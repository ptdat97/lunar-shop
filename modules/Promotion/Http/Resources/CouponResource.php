<?php

namespace Modules\Promotion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable JSON contract for an applicable coupon (cart "available coupons" list).
 *
 * @mixin \Lunar\Models\Discount
 */
class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->coupon,
            'name' => $this->name,
            'description' => app(\Modules\Promotion\Services\PromotionService::class)->describe($this->resource),
        ];
    }
}
