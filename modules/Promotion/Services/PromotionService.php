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
            // `data` + `type` are needed so describe() can summarise each coupon.
            ->get(['id', 'coupon', 'name', 'type', 'data']);
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

    /**
     * Validate a coupon code WITHOUT applying it — for live storefront feedback
     * before the shopper commits. Mirrors the apply-time checks (active + usable)
     * so a code that validates here will also apply (subject to cart contents).
     *
     * @return array{valid:bool, code:string, name?:string, description?:string, message?:string}
     */
    public function validateCoupon(string $code): array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return ['valid' => false, 'code' => $code, 'message' => 'Enter a coupon code.'];
        }

        $discount = $this->findByCoupon($code);

        if ($discount === null) {
            return ['valid' => false, 'code' => $code, 'message' => 'This coupon code is invalid or has expired.'];
        }

        return [
            'valid' => true,
            'code' => $code,
            'name' => $discount->name,
            'description' => $this->describe($discount),
        ];
    }

    /**
     * A short human-readable summary of what a discount does (e.g. "10% off",
     * "$5 off"). Falls back to the discount name for types we don't special-case.
     */
    public function describe(Discount $discount): string
    {
        $data = $discount->data ?? [];

        // Percentage off.
        if (! empty($data['percentage']) && empty($data['fixed_value'])) {
            // Drop a trailing ".0" but keep whole numbers intact (10 → "10").
            $pct = rtrim(rtrim(number_format((float) $data['percentage'], 2, '.', ''), '0'), '.');

            return "{$pct}% off";
        }

        // Fixed amount off — value is per-currency in `fixed_values`.
        if (! empty($data['fixed_value']) && ! empty($data['fixed_values'])) {
            $currency = \Lunar\Models\Currency::getDefault();
            $minor = (int) ($data['fixed_values'][$currency?->code] ?? 0);

            if ($minor > 0 && $currency) {
                $amount = $minor / (10 ** ($currency->decimal_places ?? 2));

                return \Illuminate\Support\Number::currency($amount, $currency->code) . ' off';
            }
        }

        return $discount->name;
    }
}