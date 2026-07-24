<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Modules\Catalog\Models\ProductSku;

/**
 * Thin wrapper over Lunar's CartSession (inherited — not reimplemented).
 * Cart state is server-side; this is the single entry point for web + API.
 */
class CartService
{
    /**
     * Get the current cart, calculated (line + cart totals populated).
     */
    public function current(): Cart
    {
        return $this->mutableCart()->calculate();
    }

    /**
     * The current cart WITHOUT running the calculate pipeline — for mutators,
     * which calculate once after the mutation anyway (running it before too
     * would double the pipeline work on every cart write).
     *
     * Fetched (auto-creating, per lunar.cart_session.auto_create) without
     * calculating: Lunar's calculate() pipeline throws a TypeError on a line
     * whose purchasable (SKU) was deleted/unpublished while it sat in the
     * cart, which would 500 the storefront. Prune those lines first, then
     * calculate on a cart with a fresh `lines` relation.
     */
    protected function mutableCart(): Cart
    {
        $cart = CartSession::current(calculate: false);

        if ($this->pruneMissingLines($cart)) {
            $cart->load('lines');
        }

        return $cart;
    }

    /**
     * Remove cart lines whose SKU no longer exists. Returns true if any line
     * was removed. Because saving a product's variants is delete-and-recreate,
     * a SKU a shopper added can genuinely vanish (or be soft-deleted) between
     * requests — those stale lines must go before Lunar recalculates.
     */
    protected function pruneMissingLines(Cart $cart): bool
    {
        $missing = $cart->lines()
            ->where('purchasable_type', (new ProductSku)->getMorphClass())
            ->whereNotIn('purchasable_id', ProductSku::query()->select('id'))
            ->pluck('id');

        if ($missing->isEmpty()) {
            return false;
        }

        CartLine::whereIn('id', $missing)->delete();

        return true;
    }

    /**
     * The distinct products currently in the cart, with the relations the
     * product card / recommendations need. Single source so callers don't reach
     * into cart line internals.
     *
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        $cart = $this->current()->loadMissing(
            'lines.purchasable.product.skus',
            'lines.purchasable.product.thumbnail',
        );

        return $cart->lines
            ->map(fn (CartLine $line) => $line->purchasable?->product)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Add a SKU to the cart.
     *
     * The `$skuId` is the surrogate id of the currently-selected SKU as the
     * storefront rendered it. It is only used to look the SKU up right now;
     * once in the cart, Lunar records the purchasable by morph id and the SKU's
     * stable `sku` string travels onto the order line as the identifier.
     *
     * @throws ValidationException
     */
    public function add(int $skuId, int $quantity = 1): Cart
    {
        $sku = ProductSku::findOrFail($skuId);
        $cart = $this->mutableCart();

        // A disabled SKU must never enter the cart, no matter how the request
        // reached us (storefront hides it, but this is the real guard).
        $this->guardStatus($sku);

        // Guard the RESULTING quantity, not the increment. Checking `$quantity`
        // alone let a shopper past the last unit by adding 1 five times over.
        $this->guardStock($sku, $this->quantityInCart($cart, $skuId) + $quantity);

        return $cart->add($sku, $quantity)->calculate();
    }

    /**
     * Update a line's quantity.
     *
     * @throws ValidationException
     */
    public function updateLine(int $lineId, int $quantity): Cart
    {
        $cart = $this->mutableCart();
        $line = $cart->lines->firstWhere('id', $lineId);

        // This path had no guard at all: PATCH quantity=999 on a SKU stocked
        // at 3 was accepted, and only blew up at checkout.
        if ($line && $line->purchasable instanceof ProductSku) {
            $this->guardStatus($line->purchasable);
            $this->guardStock($line->purchasable, $quantity);
        }

        return $cart->updateLine($lineId, $quantity)->calculate();
    }

    /**
     * How many units of a SKU the cart already holds.
     */
    protected function quantityInCart(Cart $cart, int $skuId): int
    {
        return (int) $cart->lines
            ->where('purchasable_type', (new ProductSku)->getMorphClass())
            ->where('purchasable_id', $skuId)
            ->sum('quantity');
    }

    /**
     * Refuse a quantity the SKU cannot fulfil (its own on-hand stock).
     *
     * Reads `quantity` (and `status`) FRESH from the DB rather than trusting the
     * SKU hydrated onto the cached cart line: on the updateLine path that line
     * can be stale (another order or an admin edit moved stock meanwhile), which
     * would let a PATCH sail past the guard against a number that no longer holds.
     *
     * @throws ValidationException
     */
    protected function guardStock(ProductSku $sku, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $fresh = ProductSku::query()
            ->select(['quantity', 'committed', 'status'])
            ->whereKey($sku->getKey())
            ->first();

        // SELLABLE stock, not the shelf count: units committed to an order that
        // hasn't shipped are physically present but already sold. Reading
        // `quantity` here lets them into a second cart, and the rejection then
        // surfaces from Lunar's own cart validator as an unhandled CartException
        // (a 500) instead of this 422.
        $available = $fresh && $fresh->status !== 'disabled' ? $fresh->getTotalInventory() : 0;

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => 'Sorry, there isn\'t enough stock to add that quantity.',
            ]);
        }
    }

    /**
     * Refuse a SKU the admin has disabled. This is the enforcement point — the
     * storefront hides disabled SKUs, but hiding a button is not a guard
     * (§17.4): a direct API call must still be rejected here.
     *
     * @throws ValidationException
     */
    protected function guardStatus(ProductSku $sku): void
    {
        // Read the status fresh: on updateLine the SKU comes off the cached
        // cart line, which can be stale if the admin disabled it meanwhile.
        $status = ProductSku::whereKey($sku->getKey())->value('status');

        if ($status === 'disabled') {
            throw ValidationException::withMessages([
                'variant' => 'Sorry, this variant is no longer available.',
            ]);
        }
    }

    /**
     * Remove a line.
     */
    public function remove(int $lineId): Cart
    {
        return $this->mutableCart()->remove($lineId)->calculate();
    }

    /**
     * Forget the current cart from the session (e.g. after an order is placed).
     */
    public function forget(): void
    {
        CartSession::forget();
    }

    /**
     * Apply a coupon code to the cart (Lunar resolves the matching discount).
     * Validates the code exists + is active/usable before applying; throws a
     * ValidationException with a clear message otherwise.
     *
     * @throws ValidationException
     */
    public function applyCoupon(string $code): Cart
    {
        $code = strtoupper(trim($code));

        $discount = Discount::query()
            ->whereRaw('UPPER(coupon) = ?', [$code])
            ->active()
            ->usable()
            ->first();

        if (! $discount) {
            throw ValidationException::withMessages([
                'code' => 'This coupon code is invalid or has expired.',
            ]);
        }

        $cart = $this->mutableCart();

        // Remember what was applied before, so a code that doesn't stick restores
        // the previous coupon rather than wiping it. Without this, submitting a
        // non-applying code silently drops an already-working discount.
        $previousCode = $cart->coupon_code;

        $cart->update(['coupon_code' => $code]);
        $cart = $cart->fresh()->calculate();

        // The code is valid, but it may not apply to this cart's contents
        // (e.g. minimum spend / product restrictions). Surface that clearly and
        // roll back to whatever coupon (if any) was applied before this attempt.
        if (blank($cart->discountTotal) || $cart->discountTotal->value <= 0) {
            $cart->update(['coupon_code' => $previousCode]);

            // Replace the session's memoized cart with the restored one, else a
            // subsequent CartSession::current() serves the in-memory copy still
            // carrying the rejected code (the DB is right, the session isn't).
            CartSession::use($cart->fresh())->calculate();

            throw ValidationException::withMessages([
                'code' => 'This coupon does not apply to the items in your cart.',
            ]);
        }

        return $cart;
    }

    /**
     * Remove the coupon from the cart.
     */
    public function removeCoupon(): Cart
    {
        $cart = $this->mutableCart();
        $cart->update(['coupon_code' => null]);

        return $cart->fresh()->calculate();
    }
}
