<?php

namespace Modules\Promotion\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Lunar\DataTypes\Price;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Modules\Catalog\Services\PricingService;
use Modules\Promotion\DiscountTypes\ComboPercentageOff;
use Modules\Promotion\DiscountTypes\QuantityPercentageOff;

/**
 * Turns discounts into storefront display data: sale badges (with struck /
 * sale price when the break is unconditional), banner view-models, short
 * human-readable summaries and the "applied discounts" rows of a calculated
 * cart. Stateless compute — memoization lives in PromotionService (the
 * request-scoped singleton). Split out of PromotionService (coding
 * standards §15).
 */
class SaleBadgeService
{
    public function __construct(protected PromotionTargetResolver $targets) {}

    /**
     * Best displayable promotion for a product, as a badge payload: a label
     * plus — when the break is unconditional for this product (a percentage
     * flash-sale/sale not gated on cart contents) — the struck original price
     * and the discounted sale price.
     *
     * Quantity/combo deals depend on cart contents, so they surface as a
     * label only (no price rewrite). Returns null when nothing applies.
     *
     * Assumes $product has ['variants', 'collections'] loaded and $promotions
     * is the pre-filtered displayable set (see PromotionService).
     *
     * @param  Collection<int, Discount>  $promotions
     * @return array{
     *   label:string,
     *   is_flash_sale:bool,
     *   has_price_break:bool,
     *   percentage:?float,
     *   original:?string,
     *   sale:?string,
     *   ends_at:?string
     * }|null
     */
    public function saleFor(Product $product, Collection $promotions): ?array
    {
        $applicable = $promotions->filter(
            fn (Discount $d) => $this->targets->appliesToProduct($d, $product)
        );

        if ($applicable->isEmpty()) {
            return null;
        }

        $priceBreak = $applicable->first(fn (Discount $d) => $this->targets->productPercentage($d) !== null);
        $discount = $priceBreak ?? $applicable->first();

        $pct = $this->targets->productPercentage($discount);
        $base = [
            'label' => $this->badge($discount),
            'is_flash_sale' => (bool) (($discount->data ?? [])['flash_sale'] ?? false),
            'has_price_break' => false,
            'percentage' => $pct,
            'original' => null,
            'sale' => null,
            'ends_at' => $discount->ends_at?->toIso8601String(),
        ];

        if ($pct === null) {
            return $base;
        }

        $price = $this->productPrice($product);

        if ($price === null) {
            return $base;
        }

        $saleValue = (int) round($price->value * (1 - $pct / 100));

        return array_merge($base, [
            'has_price_break' => true,
            'original' => (string) $price->formatted(),
            'sale' => (string) (new Price($saleValue, $price->currency, 1))->formatted(),
        ]);
    }

    /**
     * Promotions actually applied to a calculated cart, derived from Lunar's
     * discount breakdown. Each entry labels the discount + total saving, so the
     * mini-cart / cart page / checkout can show "Flash Sale −$5.00" rows. One
     * row per discount (a discount may affect several lines).
     *
     * @return array<int, array{name:string, description:string, amount:string, is_flash_sale:bool}>
     */
    public function appliedTo(Cart $cart): array
    {
        $breakdown = $cart->discountBreakdown;

        if (! $breakdown || $breakdown->isEmpty()) {
            return [];
        }

        return collect($breakdown)
            ->groupBy(fn ($entry) => $entry->discount->id)
            ->map(function ($entries) {
                $discount = $entries->first()->discount;
                $amount = $entries->sum(fn ($entry) => $entry->price->value);
                $currency = $entries->first()->price->currency;

                return [
                    'name' => $discount->name,
                    'description' => $this->describe($discount),
                    'amount' => (string) (new Price($amount, $currency, 1))->formatted(),
                    'is_flash_sale' => (bool) (($discount->data ?? [])['flash_sale'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Lightweight view-model for a discount, for storefront promo display.
     *
     * @return array{name:string, description:string, ends_at:?string, is_flash_sale:bool}
     */
    public function toBanner(Discount $discount): array
    {
        return [
            'name' => $discount->name,
            'description' => $this->describe($discount),
            'ends_at' => $discount->ends_at?->toIso8601String(),
            'is_flash_sale' => (bool) (($discount->data ?? [])['flash_sale'] ?? false),
        ];
    }

    /**
     * A short badge label for a product card (e.g. "-20%", "Buy 2 -10%").
     */
    public function badge(Discount $discount): string
    {
        $data = $discount->data ?? [];

        if ($discount->type === QuantityPercentageOff::class) {
            $minQty = (int) ($data['min_qty'] ?? 2);
            $pct = $this->trimPercentage($data['percentage'] ?? 0);

            return "Buy {$minQty} -{$pct}%";
        }

        if ($discount->type === ComboPercentageOff::class) {
            $pct = $this->trimPercentage($data['percentage'] ?? 0);

            return "Combo -{$pct}%";
        }

        if (! empty($data['percentage']) && empty($data['fixed_value'])) {
            return '-'.$this->trimPercentage($data['percentage']).'%';
        }

        if (($data['flash_sale'] ?? false)) {
            return 'Flash Sale';
        }

        return 'Sale';
    }

    /**
     * A short human-readable summary of what a discount does (e.g. "10% off",
     * "$5 off"). Falls back to the discount name for types we don't special-case.
     */
    public function describe(Discount $discount): string
    {
        $data = $discount->data ?? [];

        // Quantity deal: "Buy N, get X% off".
        if ($discount->type === QuantityPercentageOff::class) {
            $minQty = (int) ($data['min_qty'] ?? 2);
            $pct = $this->trimPercentage($data['percentage'] ?? 0);

            return "Buy {$minQty}, get {$pct}% off";
        }

        // Combo deal: "Buy across N groups, get X% off".
        if ($discount->type === ComboPercentageOff::class) {
            $pct = $this->trimPercentage($data['percentage'] ?? 0);

            return "Buy the combo, get {$pct}% off";
        }

        // Percentage off.
        if (! empty($data['percentage']) && empty($data['fixed_value'])) {
            return $this->trimPercentage($data['percentage']).'% off';
        }

        // Fixed amount off — value is per-currency in `fixed_values`.
        if (! empty($data['fixed_value']) && ! empty($data['fixed_values'])) {
            $currency = Currency::getDefault();
            $minor = (int) ($data['fixed_values'][$currency?->code] ?? 0);

            if ($minor > 0 && $currency) {
                $amount = $minor / (10 ** ($currency->decimal_places ?? 2));

                return Number::currency($amount, $currency->code).' off';
            }
        }

        return $discount->name;
    }

    /**
     * The first variant's matched price for a product, or null. Delegates to
     * the Pricing service so the pricing engine is invoked in one place.
     */
    protected function productPrice(Product $product): ?Price
    {
        $variant = $product->variants->first() ?? $product->variants()->first();

        if (! $variant) {
            return null;
        }

        return app(PricingService::class)->matchedPrice($variant);
    }

    /**
     * Format a percentage, dropping a trailing ".0" but keeping whole numbers
     * intact (10 → "10", 12.5 → "12.5").
     */
    protected function trimPercentage(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
