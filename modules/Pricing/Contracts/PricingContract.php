<?php

namespace Modules\Pricing\Contracts;

use Lunar\DataTypes\Price as PriceData;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

/**
 * The pricing service contract — the single seam callers (Blade components,
 * Resources, Promotion) resolve. Extracted so the implementation can be
 * decorated (e.g. membership / flash-sale pricing) without editing it. Mirrors
 * PricingService's public API exactly; binding stays the existing class.
 */
interface PricingContract
{
    public function matchedPrice(ProductVariant $variant): ?PriceData;

    public function displayPrice(Product $product): ?string;

    public function defaultCurrencyCode(): string;

    public function lowestPriceAmount(Product $product): ?float;

    public function variantPrice(int $variantId, ?int $currencyId = null): ?Price;

    public function hasTieredPricing(int $variantId): bool;

    /** @return \Illuminate\Database\Eloquent\Collection<int, Price> */
    public function customerGroupPrices(int $variantId, int $customerGroupId);
}
