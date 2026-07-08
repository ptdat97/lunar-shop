<?php

namespace Modules\Promotion\Services;

use Illuminate\Support\Collection;
use Lunar\Models\Cart;
use Lunar\Models\Discount;
use Lunar\Models\Product;

/**
 * Single entry point the rest of the app uses for promotions. Owns the
 * discount queries and the per-request memoization (registered as a
 * singleton), and delegates:
 *  - targeting / eligibility → PromotionTargetResolver
 *  - storefront display (badges, banners, summaries) → SaleBadgeService
 *
 * The public API is unchanged by the split — callers (resources, view
 * composers, controllers) keep talking to this one service.
 */
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
     * Per-request badge memo — keyed by product ID so that the view composer
     * loop over a product grid (24+ cards) resolves every sale badge with
     * exactly ONE pass through the promotion matching logic.
     *
     * @var array<int, array|null> productId => saleFor() result
     */
    private array $saleMemo = [];

    public function __construct(
        protected PromotionTargetResolver $targets,
        protected SaleBadgeService $badges,
    ) {}

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
            ->reject(fn (Discount $d) => $this->targets->isMembershipDiscount($d))
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
            ->filter(fn (Discount $d) => $this->targets->isDisplayablePromotion($d))
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
     * @return Collection<int, Product>
     */
    public function productsForPromotion(Discount $discount, int $limit = 12): Collection
    {
        $query = Product::query()
            ->where('status', 'published')
            // Eager-load everything a product card renders (flat, not N+1).
            // `media` powers the card hover (second) image.
            ->with(['variants.prices.currency', 'thumbnail', 'brand', 'collections', 'defaultUrl', 'media']);

        $productIds = $this->targets->targetedProductIds($discount);
        $collectionIds = $this->targets->targetedCollectionIds($discount);

        if ($productIds->isNotEmpty() || $collectionIds->isNotEmpty()) {
            $query->where(function ($q) use ($productIds, $collectionIds) {
                if ($productIds->isNotEmpty()) {
                    $q->whereIn('id', $productIds);
                }
                if ($collectionIds->isNotEmpty()) {
                    $q->orWhereHas('collections', fn ($c) => $c->whereIn(
                        $c->getModel()->getTable().'.id', $collectionIds
                    ));
                }
            });
        }

        return $query->latest('id')->limit($limit)->get()
            // Keep only products the badge logic actually applies to.
            ->filter(fn (Product $p) => $this->targets->appliesToProduct($discount, $p))
            ->values();
    }

    /**
     * Products that currently have any displayable promotion, de-duplicated,
     * for the homepage "on sale" slider. Cart-wide flash sale dominates.
     *
     * @return Collection<int, Product>
     */
    public function productsOnSale(int $limit = 12): Collection
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
     * Batch-compute sale badges for a collection of products — one pass over
     * the promotions, zero loadMissing() calls per product.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array|null> productId => saleFor() result
     */
    public function saleForMany(Collection $products): array
    {
        $promotions = $this->displayablePromotions();

        if ($promotions->isEmpty()) {
            return $products->pluck('id')->mapWithKeys(fn ($id) => [$id => null])->all();
        }

        // Pre-load relations for ALL products at once, not per product.
        $products->loadMissing(['variants', 'collections']);

        $results = [];
        foreach ($products as $product) {
            $results[$product->id] = $this->badges->saleFor($product, $promotions);
            $this->saleMemo[$product->id] = $results[$product->id];
        }

        return $results;
    }

    /**
     * Best automatic promotion for a single product, for the product card /
     * detail page (see SaleBadgeService::saleFor for the payload shape).
     *
     * Uses per-request memoization: the FIRST call for a product pre-loads
     * relations; subsequent calls for the same product are instant.
     */
    public function saleFor(Product $product): ?array
    {
        // Return cached result if already computed this request.
        if (array_key_exists($product->id, $this->saleMemo)) {
            return $this->saleMemo[$product->id];
        }

        $promotions = $this->displayablePromotions();

        if ($promotions->isEmpty()) {
            return $this->saleMemo[$product->id] = null;
        }

        // Pre-load for this single product (same as before, but memoized).
        $product->loadMissing(['variants', 'collections']);

        return $this->saleMemo[$product->id] = $this->badges->saleFor($product, $promotions);
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

    // ------------------------------------------------------------------
    // Delegations — same public API as before the split.
    // ------------------------------------------------------------------

    /** @see PromotionTargetResolver::appliesToProduct() */
    public function appliesToProduct(Discount $discount, Product $product): bool
    {
        return $this->targets->appliesToProduct($discount, $product);
    }

    /** @see PromotionTargetResolver::isMembershipDiscount() */
    public function isMembershipDiscount(Discount $discount): bool
    {
        return $this->targets->isMembershipDiscount($discount);
    }

    /** @see PromotionTargetResolver::isDisplayablePromotion() */
    public function isDisplayablePromotion(Discount $discount): bool
    {
        return $this->targets->isDisplayablePromotion($discount);
    }

    /** @see SaleBadgeService::appliedTo() */
    public function appliedTo(Cart $cart): array
    {
        return $this->badges->appliedTo($cart);
    }

    /** @see SaleBadgeService::toBanner() */
    public function toBanner(Discount $discount): array
    {
        return $this->badges->toBanner($discount);
    }

    /** @see SaleBadgeService::badge() */
    public function badge(Discount $discount): string
    {
        return $this->badges->badge($discount);
    }

    /** @see SaleBadgeService::describe() */
    public function describe(Discount $discount): string
    {
        return $this->badges->describe($discount);
    }
}
