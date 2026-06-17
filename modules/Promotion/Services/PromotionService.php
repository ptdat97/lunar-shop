<?php

namespace Modules\Promotion\Services;

use Lunar\Models\Discount;

class PromotionService
{
    /**
     * Get all active discounts.
     */
    public function active()
    {
        return Discount::active()->usable()->get();
    }

    /**
     * Get discounts for a specific coupon code.
     */
    public function findByCoupon(string $code): ?Discount
    {
        return Discount::query()
            ->whereRaw('UPPER(coupon) = ?', [strtoupper(trim($code))])
            ->active()
            ->usable()
            ->first();
    }

    /**
     * Check if a coupon is valid.
     */
    public function couponValid(string $code): bool
    {
        return $this->findByCoupon($code) !== null;
    }
}