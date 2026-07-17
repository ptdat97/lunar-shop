<?php

namespace Modules\Promotion\Services;

use Illuminate\Support\Collection;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Modules\Catalog\Models\ProductSku;
use Modules\Promotion\DiscountTypes\ComboPercentageOff;
use Modules\Promotion\DiscountTypes\QuantityPercentageOff;

/**
 * Answers "does this discount target this product?" and related eligibility
 * questions. Pure matching logic over a Discount's limitation/condition/
 * exclusion sets — stateless, no queries beyond loadMissing on the models
 * passed in. Split out of PromotionService (coding standards §15: service
 * ≤ 500 lines, one responsibility per class).
 */
class PromotionTargetResolver
{
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
        $product->loadMissing(['collections', 'skus']);

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
     * Product ids a discount targets via limitations / quantity conditions.
     *
     * @return Collection<int, int>
     */
    public function targetedProductIds(Discount $discount): Collection
    {
        $discount->loadMissing(['discountableLimitations', 'discountableConditions']);

        return $discount->discountableLimitations
            ->merge($discount->discountableConditions)
            ->filter(fn ($d) => $d->discountable_type === Product::morphName())
            ->pluck('discountable_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Collection ids a discount targets (limitations + combo collections).
     *
     * @return Collection<int, int>
     */
    public function targetedCollectionIds(Discount $discount): Collection
    {
        $combo = collect(($discount->data ?? [])['combo_collections'] ?? [])
            ->map(fn ($id) => (int) $id);

        $limits = $discount->collections->where('pivot.type', 'limitation')->pluck('id');

        return $combo->merge($limits)->filter()->unique()->values();
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
     * Whether a discount can be shown as a storefront sale badge — i.e. we can
     * render a meaningful label for it (flash/percentage/quantity/combo with a
     * positive percentage). Excludes Lunar's BuyXGetY and degenerate rows
     * (data = null, 0%, fixed-amount with no per-product break).
     */
    public function isDisplayablePromotion(Discount $discount): bool
    {
        $data = $discount->data ?? [];

        // Our typed promos: show only when the percentage is > 0.
        if (in_array($discount->type, [QuantityPercentageOff::class, ComboPercentageOff::class], true)) {
            return (float) ($data['percentage'] ?? 0) > 0;
        }

        // Simple percentage AmountOff (flash sale / sale).
        if (! empty($data['percentage']) && empty($data['fixed_value'])) {
            return (float) $data['percentage'] > 0;
        }

        // Everything else (BuyXGetY, fixed-amount, empty data) → no badge.
        return false;
    }

    /**
     * The unconditional per-product percentage a discount takes off, or null
     * when it doesn't translate to a simple per-product break (fixed amounts,
     * quantity/combo deals that depend on cart contents).
     */
    public function productPercentage(Discount $discount): ?float
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
     * Whether a product (or one of its SKUs) is referenced by a set of
     * Discountable rows (products / SKUs only).
     */
    protected function productInDiscountables(Collection $discountables, Product $product): bool
    {
        $skuIds = $product->skus->pluck('id');
        $skuMorph = (new ProductSku)->getMorphClass();

        return $discountables->contains(function ($item) use ($product, $skuIds, $skuMorph) {
            if ($item->discountable_type === Product::morphName()) {
                return (int) $item->discountable_id === (int) $product->id;
            }

            if ($item->discountable_type === $skuMorph) {
                return $skuIds->contains((int) $item->discountable_id);
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
}
