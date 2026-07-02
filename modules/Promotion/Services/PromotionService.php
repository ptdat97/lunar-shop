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
     * Per-request memo of active automatic discounts (with relations eager
     * loaded). Product listing pages call saleFor() once per card; without this
     * each call re-queried the discounts + their pivots → hundreds of queries.
     * The service is a singleton (see provider) so this lives for the request.
     */
    private ?Collection $automaticMemo = null;

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
        if ($this->automaticMemo !== null) {
            return $this->automaticMemo;
        }

        return $this->automaticMemo = Discount::query()
            ->where(fn ($q) => $q->whereNull('coupon')->orWhere('coupon', ''))
            ->active()
            ->usable()
            ->orderByDesc('priority')
            // Eager-load every relation appliesToProduct() / isMembershipDiscount()
            // reads, so per-product badge checks hit zero extra queries.
            ->with([
                'discountableLimitations', 'discountableConditions',
                'discountableExclusions', 'collections', 'brands', 'customerGroups',
            ])
            ->get()
            ->reject(fn (Discount $d) => $this->isMembershipDiscount($d))
            ->values();
    }

    /**
     * Public, displayable automatic promotions for the storefront promotions
     * page/section (flash sale, quantity, combo — the ones we can render a
     * badge for). Highest priority first.
     *
     * @return Collection<int, Discount>
     */
    public function displayablePromotions(): Collection
    {
        return $this->activeAutomatic()
            ->filter(fn (Discount $d) => $this->isDisplayablePromotion($d))
            ->values();
    }

    /**
     * Resolve a single displayable promotion by its `handle` (used as the slug
     * for the promotion detail page), or null.
     */
    public function findPromotion(string $handle): ?Discount
    {
        return $this->displayablePromotions()
            ->first(fn (Discount $d) => $d->handle === $handle);
    }

    /**
     * Published products covered by a promotion, ready for product cards.
     * Resolves the discount's targeting (limitations / conditions / combo
     * collections); a cart-wide flash sale returns the newest products.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function productsForPromotion(Discount $discount, int $limit = 12): \Illuminate\Support\Collection
    {
        $query = Product::query()
            ->where('status', 'published')
            // Eager-load everything a product card renders (flat, not N+1).
            // `media` powers the card hover (second) image.
            ->with(['variants.prices.currency', 'thumbnail', 'brand', 'collections', 'defaultUrl', 'media']);

        $productIds = $this->targetedProductIds($discount);
        $collectionIds = $this->targetedCollectionIds($discount);

        if ($productIds->isNotEmpty() || $collectionIds->isNotEmpty()) {
            $query->where(function ($q) use ($productIds, $collectionIds) {
                if ($productIds->isNotEmpty()) {
                    $q->whereIn('id', $productIds);
                }
                if ($collectionIds->isNotEmpty()) {
                    $q->orWhereHas('collections', fn ($c) => $c->whereIn(
                        $c->getModel()->getTable() . '.id', $collectionIds
                    ));
                }
            });
        }

        return $query->latest('id')->limit($limit)->get()
            // Keep only products the badge logic actually applies to.
            ->filter(fn (Product $p) => $this->appliesToProduct($discount, $p))
            ->values();
    }

    /**
     * Products that currently have any displayable promotion, de-duplicated,
     * for the homepage "on sale" slider. Cart-wide flash sale dominates.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function productsOnSale(int $limit = 12): \Illuminate\Support\Collection
    {
        $promotions = $this->displayablePromotions();

        if ($promotions->isEmpty()) {
            return collect();
        }

        $products = collect();

        foreach ($promotions as $promotion) {
            $products = $products->merge(
                $this->productsForPromotion($promotion, $limit)
            );

            if ($products->unique('id')->count() >= $limit) {
                break;
            }
        }

        return $products->unique('id')->take($limit)->values();
    }

    /**
     * Collection ids a discount targets (limitations + combo collections).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function targetedCollectionIds(Discount $discount): \Illuminate\Support\Collection
    {
        $combo = collect(($discount->data ?? [])['combo_collections'] ?? [])
            ->map(fn ($id) => (int) $id);

        $limits = $discount->collections->where('pivot.type', 'limitation')->pluck('id');

        return $combo->merge($limits)->filter()->unique()->values();
    }

    /**
     * Product ids a discount targets via limitations / quantity conditions.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function targetedProductIds(Discount $discount): \Illuminate\Support\Collection
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
     * Per-request badge memo — keyed by product ID so that the view composer
     * loop over a product grid (24+ cards) resolves every sale badge with
     * exactly ONE pass through the promotion matching logic.
     *
     * @var array<int, array|null>  productId => saleFor() result
     */
    private array $saleMemo = [];

    /**
     * Batch-compute sale badges for a collection of products — one pass over
     * the promotions, zero loadMissing() calls per product.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return array<int, array|null>  productId => saleFor() result
     */
    public function saleForMany(\Illuminate\Support\Collection $products): array
    {
        $promotions = $this->activeAutomatic()
            ->filter(fn (Discount $d) => $this->isDisplayablePromotion($d));

        if ($promotions->isEmpty()) {
            return $products->pluck('id')->mapWithKeys(fn ($id) => [$id => null])->all();
        }

        // Pre-load relations for ALL products at once, not per product.
        $products->loadMissing(['variants', 'collections']);

        $results = [];
        foreach ($products as $product) {
            $results[$product->id] = $this->computeSaleFor($product, $promotions);
            $this->saleMemo[$product->id] = $results[$product->id];
        }

        return $results;
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
     * Uses per-request memoization: the FIRST call for a product pre-loads
     * relations; subsequent calls for the same product are instant.
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
        // Return cached result if already computed this request.
        if (array_key_exists($product->id, $this->saleMemo)) {
            return $this->saleMemo[$product->id];
        }

        $promotions = $this->activeAutomatic()
            ->filter(fn (Discount $d) => $this->isDisplayablePromotion($d));

        if ($promotions->isEmpty()) {
            return $this->saleMemo[$product->id] = null;
        }

        // Pre-load for this single product (same as before, but memoized).
        $product->loadMissing(['variants', 'collections']);

        return $this->saleMemo[$product->id] = $this->computeSaleFor($product, $promotions);
    }

    /**
     * Core badge computation — shared between saleFor() and saleForMany().
     * Assumes $product has ['variants', 'collections'] loaded.
     *
     * @param  \Illuminate\Support\Collection<int, Discount>  $promotions
     * @return array|null
     */
    protected function computeSaleFor(Product $product, \Illuminate\Support\Collection $promotions): ?array
    {
        $applicable = $promotions->filter(fn (Discount $d) => $this->appliesToProduct($d, $product));

        if ($applicable->isEmpty()) {
            return null;
        }

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

        return app(\Modules\Catalog\Services\PricingService::class)->matchedPrice($variant);
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