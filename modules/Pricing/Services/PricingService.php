<?php

namespace Modules\Pricing\Services;

use Lunar\Models\Price;
use Lunar\Models\ProductVariant;

class PricingService
{
    /**
     * Get the price for a variant in a given currency.
     */
    public function variantPrice(int $variantId, ?int $currencyId = null): ?Price
    {
        $query = Price::where('priceable_type', ProductVariant::class)
            ->where('priceable_id', $variantId);

        if ($currencyId) {
            $query->where('currency_id', $currencyId);
        }

        return $query->first();
    }

    /**
     * Check if a variant has a tiered price.
     */
    public function hasTieredPricing(int $variantId): bool
    {
        return Price::where('priceable_type', ProductVariant::class)
            ->where('priceable_id', $variantId)
            ->where('tier', '>', 1)
            ->exists();
    }

    /**
     * Get prices for a customer group.
     */
    public function customerGroupPrices(int $variantId, int $customerGroupId)
    {
        return Price::where('priceable_type', ProductVariant::class)
            ->where('priceable_id', $variantId)
            ->where('customer_group_id', $customerGroupId)
            ->get();
    }
}