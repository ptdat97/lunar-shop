<?php

namespace Modules\Catalog\Services;

use Lunar\DataTypes\Price as PriceData;
use Lunar\Facades\Pricing;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class PricingService
{
    /**
     * Per-request memo of matched prices, keyed by variant ID.
     * Prevents re-running Lunar's pricing engine for the same variant
     * across multiple view composers (product-card, price, product page).
     *
     * @var array<int, PriceData|null>
     */
    private array $priceMemo = [];

    /**
     * Per-request map of ALL currencies keyed by id (the table holds a handful
     * of rows). Used to prime price->currency without eager-loading `currency`
     * in every product pipeline — each of those eager loads was one identical
     * `lunar_currencies` query per section/page. One query here covers every
     * price in the request, in any currency.
     *
     * @var array<int, \Lunar\Models\Currency>|null
     */
    private ?array $currenciesById = null;

    /** @return array<int, \Lunar\Models\Currency> */
    protected function currenciesById(): array
    {
        return $this->currenciesById ??= \Lunar\Models\Currency::all()->keyBy('id')->all();
    }

    /**
     * The matched price for a variant via Lunar's Pricing engine (honours
     * currency, customer group and tiers). This is the single place the
     * pricing engine is invoked for presentation — Blade/Resources call here
     * instead of touching the `Pricing` facade directly (keeps price logic out
     * of views, per the coding standards).
     *
     * Results are memoized per-request so the same variant queried by
     * product-card, price component, and product page composers resolves
     * in O(1) after the first call.
     */
    public function matchedPrice(ProductVariant $variant): ?PriceData
    {
        $variantId = (int) $variant->id;

        if (array_key_exists($variantId, $this->priceMemo)) {
            return $this->priceMemo[$variantId];
        }

        try {
            // Prime the inverse relation: Lunar's Price cast reads
            // $price->priceable->unit_quantity, which lazy-loads the variant
            // again (one query per price) unless we point it back at the variant
            // we already have. Saves a query per product card on listing pages.
            //
            // Also prime the price->currency relation from the per-request
            // currency map, so product pipelines don't need to eager-load
            // `prices.currency` at all (that eager load repeated an identical
            // `lunar_currencies` query per section) and downstream formatting
            // (e.g. PromotionService reading $matched->currency for a sale
            // badge) never lazy-loads a currency per product.
            if ($variant->relationLoaded('prices')) {
                $currencies = $this->currenciesById();
                $variant->prices->each(function (Price $price) use ($variant, $currencies): void {
                    $price->setRelation('priceable', $variant);
                    if (! $price->relationLoaded('currency')
                        && isset($currencies[(int) $price->currency_id])) {
                        $price->setRelation('currency', $currencies[(int) $price->currency_id]);
                    }
                });
            }

            return $this->priceMemo[$variantId] = Pricing::for($variant)->get()->matched->price;
        } catch (\Throwable $e) {
            return $this->priceMemo[$variantId] = null;
        }
    }

    /**
     * Formatted display price for a product's first variant (e.g. "$60.00"),
     * or null when it can't be resolved. Used by the <x-price> component.
     */
    public function displayPrice(Product $product): ?string
    {
        $variant = $product->variants->first() ?? $product->variants()->first();

        return $variant ? (string) $this->matchedPrice($variant)?->formatted() : null;
    }

    /**
     * Formatted display price for a specific variant (e.g. a deep-linked variant
     * on the product page). Null when the variant can't be priced.
     */
    public function displayPriceForVariant(?ProductVariant $variant): ?string
    {
        return $variant ? (string) $this->matchedPrice($variant)?->formatted() : null;
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