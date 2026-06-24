<?php

namespace Modules\Promotion\Services;

use Illuminate\Support\Collection;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Modules\Promotion\DiscountTypes\ComboPercentageOff;
use Modules\Promotion\DiscountTypes\QuantityPercentageOff;

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
     * Active automatic (non-coupon) PUBLIC promotions every shopper benefits
     * from without entering a code — flash sales, quantity deals, combos. Used
     * to render storefront promo banners/badges.
     *
     * Membership perks are excluded: they're personalised (scoped to a loyalty
     * customer group), so they belong on the account page, not on public cards.
     *
     * @return Collection<int, Discount>
     */
    public function activeAutomatic(): Collection
    {
        return Discount::query()
            ->where(fn ($q) => $q->whereNull('coupon')->orWhere('coupon', ''))
            ->active()
            ->usable()
            ->orderByDesc('priority')
            ->get()
            ->reject(fn (Discount $d) => $this->isMembershipDiscount($d))
            ->values();
    }

    /**
     * Whether a discount is a personalised membership/loyalty perk (flagged via
     * `data.membership`, or scoped to a non-default customer group).
     */
    public function isMembershipDiscount(Discount $discount): bool
    {
        if (($discount->data ?? [])['membership'] ?? false) {
            return true;
        }

        $groups = $discount->relationLoaded('customerGroups')
            ? $discount->customerGroups
            : $discount->customerGroups()->get();

        return $groups->isNotEmpty() && $groups->every(fn ($g) => ! $g->default);
    }

    /**
     * Promotions actually applied to a calculated cart, derived from Lunar's
     * discount breakdown. Each entry labels the discount + total saving, so the
     * mini-cart / cart page / checkout can show "Flash Sale −$5.00" rows. One
     * row per discount (a discount may affect several lines).
     *
     * @return array<int, array{name:string, description:string, amount:string, is_flash_sale:bool}>
     */
    public function appliedTo(\Lunar\Models\Cart $cart): array
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
                    'amount' => (string) (new \Lunar\DataTypes\Price($amount, $currency, 1))->formatted(),
                    'is_flash_sale' => (bool) (($discount->data ?? [])['flash_sale'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The current flash sale, if any — a time-boxed promotion flagged with
     * `data.flash_sale = true` and an end date, soonest to expire first so the
     * banner counts down the most urgent one.
     */
    public function currentFlashSale(): ?Discount
    {
        return Discount::query()
            ->whereNotNull('ends_at')
            ->active()
            ->usable()
            ->get()
            ->filter(fn (Discount $d) => (bool) (($d->data ?? [])['flash_sale'] ?? false))
            ->sortBy('ends_at')
            ->first();
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
     * Best automatic promotion for a single product, for the product card /
     * detail page: a badge label plus — when the break is unconditional for
     * this product (a percentage flash-sale/sale not gated on cart contents) —
     * the struck original price and the discounted sale price.
     *
     * Quantity/combo deals depend on cart contents, so they surface as a
     * label only (no price rewrite). Returns null when nothing applies.
     *
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
    public function saleFor(Product $product): ?array
    {
        $applicable = $this->activeAutomatic()->filter(
            fn (Discount $d) => $this->appliesToProduct($d, $product)
        );

        if ($applicable->isEmpty()) {
            return null;
        }

        // Prefer a discount that yields a concrete per-product price break
        // (a simple percentage), falling back to the highest-priority one.
        $priceBreak = $applicable->first(fn (Discount $d) => $this->productPercentage($d) !== null);
        $discount = $priceBreak ?? $applicable->first();

        $pct = $this->productPercentage($discount);
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
            'sale' => (string) (new \Lunar\DataTypes\Price($saleValue, $price->currency, 1))->formatted(),
        ]);
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
            return '-' . $this->trimPercentage($data['percentage']) . '%';
        }

        if (($data['flash_sale'] ?? false)) {
            return 'Flash Sale';
        }

        return 'Sale';
    }

    /**
     * The first variant's matched price for a product, or null. Delegates to
     * the Pricing service so the pricing engine is invoked in one place.
     */
    protected function productPrice(Product $product): ?\Lunar\DataTypes\Price
    {
        $variant = $product->variants->first() ?? $product->variants()->first();

        if (! $variant) {
            return null;
        }

        return app(\Modules\Pricing\Services\PricingService::class)->matchedPrice($variant);
    }

    /**
     * The unconditional per-product percentage a discount takes off, or null
     * when it doesn't translate to a simple per-product break (fixed amounts,
     * quantity/combo deals that depend on cart contents).
     */
    protected function productPercentage(Discount $discount): ?float
    {
        // Quantity/combo deals are cart-conditional → no per-product break.
        if (in_array($discount->type, [QuantityPercentageOff::class, ComboPercentageOff::class], true)) {
            return null;
        }

        $data = $discount->data ?? [];

        if (empty($data['percentage']) || ! empty($data['fixed_value'])) {
            return null;
        }

        return (float) $data['percentage'];
    }

    /**
     * Whether a discount targets a given product, via its limitation set
     * (products / variants / collections / brands). No limitations = cart-wide,
     * so it applies to every product. Exclusions remove the product.
     *
     * The custom quantity/combo types scope eligibility differently from the
     * native limitation set, so they're handled first:
     *  - QuantityPercentageOff → its `discountableConditions` (or cart-wide).
     *  - ComboPercentageOff    → `data.combo_collections`.
     */
    public function appliesToProduct(Discount $discount, Product $product): bool
    {
        $product->loadMissing(['collections', 'variants']);

        if ($discount->type === ComboPercentageOff::class) {
            $groups = collect(($discount->data ?? [])['combo_collections'] ?? [])
                ->map(fn ($id) => (int) $id);

            return $groups->isNotEmpty()
                && $product->collections->pluck('id')->intersect($groups)->isNotEmpty();
        }

        if ($discount->type === QuantityPercentageOff::class) {
            $discount->loadMissing('discountableConditions');
            $conditions = $discount->discountableConditions;

            // No conditions → applies to any line (the threshold is the gate).
            if ($conditions->isEmpty()) {
                return true;
            }

            return $this->productMatchesDiscountables($conditions, $product);
        }

        $discount->loadMissing([
            'discountableLimitations', 'discountableExclusions', 'collections', 'brands',
        ]);

        // Exclusions win: if the product is excluded, it never applies.
        if ($this->productInDiscountables($discount->discountableExclusions, $product)) {
            return false;
        }

        $collectionLimits = $discount->collections->where('pivot.type', 'limitation')->pluck('id');
        $brandLimits = $discount->brands->where('pivot.type', 'limitation')->pluck('id');
        $productLimits = $discount->discountableLimitations;

        $hasAnyLimit = $collectionLimits->isNotEmpty()
            || $brandLimits->isNotEmpty()
            || $productLimits->isNotEmpty();

        // No limitations of any kind → cart-wide, applies to everything.
        if (! $hasAnyLimit) {
            return true;
        }

        if ($collectionLimits->isNotEmpty()
            && $product->collections->pluck('id')->intersect($collectionLimits)->isNotEmpty()) {
            return true;
        }

        if ($brandLimits->isNotEmpty() && $brandLimits->contains($product->brand_id)) {
            return true;
        }

        if ($productLimits->isNotEmpty() && $this->productInDiscountables($productLimits, $product)) {
            return true;
        }

        return false;
    }

    /**
     * Whether a product (or one of its variants) is referenced by a set of
     * Discountable rows (products / variants only).
     */
    protected function productInDiscountables(Collection $discountables, Product $product): bool
    {
        $variantIds = $product->variants->pluck('id');

        return $discountables->contains(function ($item) use ($product, $variantIds) {
            if ($item->discountable_type === Product::morphName()) {
                return (int) $item->discountable_id === (int) $product->id;
            }

            if ($item->discountable_type === \Lunar\Models\ProductVariant::morphName()) {
                return $variantIds->contains((int) $item->discountable_id);
            }

            return false;
        });
    }

    /**
     * Like {@see productInDiscountables} but also matches collection-typed rows
     * (the quantity type's conditions may target a collection).
     */
    protected function productMatchesDiscountables(Collection $discountables, Product $product): bool
    {
        if ($this->productInDiscountables($discountables, $product)) {
            return true;
        }

        $collectionIds = $product->collections->pluck('id');

        return $discountables->contains(
            fn ($item) => $item->discountable_type === \Lunar\Models\Collection::morphName()
                && $collectionIds->contains((int) $item->discountable_id)
        );
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
            return $this->trimPercentage($data['percentage']) . '% off';
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

    /**
     * Format a percentage, dropping a trailing ".0" but keeping whole numbers
     * intact (10 → "10", 12.5 → "12.5").
     */
    protected function trimPercentage(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}