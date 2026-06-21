<?php

namespace Modules\Promotion\Services;

use Illuminate\Support\Collection;
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
     * Active coupon-based discounts a shopper can apply at the cart, highest
     * priority first.
     *
     * @return Collection<int, Discount>
     */
    public function availableCoupons(): Collection
    {
        return Discount::query()
            ->whereNotNull('coupon')
            ->active()
            ->usable()
            ->orderByDesc('priority')
            ->get(['id', 'coupon', 'name']);
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