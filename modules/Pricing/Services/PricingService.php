<?php

namespace Modules\Pricing\Services;

use Lunar\DataTypes\Price as PriceData;
use Lunar\Facades\Pricing;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class PricingService
{
    /**
     * The matched price for a variant via Lunar's Pricing engine (honours
     * currency, customer group and tiers). This is the single place the
     * pricing engine is invoked for presentation — Blade/Resources call here
     * instead of touching the `Pricing` facade directly (keeps price logic out
     * of views, per the coding standards).
     */
    public function matchedPrice(ProductVariant $variant): ?PriceData
    {
        try {
            // Prime the inverse relation: Lunar's Price cast reads
            // $price->priceable->unit_quantity, which lazy-loads the variant
            // again (one query per price) unless we point it back at the variant
            // we already have. Saves a query per product card on listing pages.
            if ($variant->relationLoaded('prices')) {
                $variant->prices->each(fn (Price $price) => $price->setRelation('priceable', $variant));
            }

            return Pricing::for($variant)->get()->matched->price;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Formatted display price for a product's first variant (e.g. "$60.00"),
     * or null when it can't be resolved. Used by the <x-price> component.
     */
    public function displayPrice(Product $product): ?string
    {
        $variant = $product->variants->first() ?? $product->variants()->first();

        if (! $variant) {
            return null;
        }

        return (string) $this->matchedPrice($variant)?->formatted();
    }

    /**
     * The store's default currency code (e.g. "USD", "VND"), for display /
     * structured data. Falls back to "USD" when none is configured.
     */
    public function defaultCurrencyCode(): string
    {
        return \Lunar\Models\Currency::getDefault()?->code ?? 'USD';
    }

    /**
     * Lowest variant price (decimal) across a product's variants, for
     * structured data (JSON-LD Offer). Null when no variant is priced.
     */
    public function lowestPriceAmount(Product $product): ?float
    {
        return $product->variants
            ->map(fn (ProductVariant $variant) => $this->matchedPrice($variant)?->decimal())
            ->filter()
            ->min();
    }

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