<?php

namespace Modules\Promotion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Promotion\Services\PromotionService;

/**
 * Stable JSON contract for an automatic (non-coupon) promotion shown on the
 * storefront — flash sale, quantity deal, combo, membership perk.
 *
 * @mixin \Lunar\Models\Discount
 */
class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $promotions = app(PromotionService::class);

        return [
            'name' => $this->name,
            'description' => $promotions->describe($this->resource),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_flash_sale' => (bool) (($this->data ?? [])['flash_sale'] ?? false),
        ];
    }
}
