<?php

namespace Modules\Cart\Services;

use Illuminate\Support\Collection;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Modules\Cart\Contracts\CartContract;

/**
 * Thin wrapper over Lunar's CartSession (inherited — not reimplemented).
 * Cart state is server-side; this is the single entry point for web + API.
 */
class CartService implements CartContract
{
    /**
     * Get the current cart, calculated (line + cart totals populated).
     */
    public function current(): Cart
    {
        // Fetch (auto-creating, per lunar.cart_session.auto_create) WITHOUT
        // calculating yet: Lunar's calculate() pipeline throws a TypeError on a
        // line whose purchasable (variant) was deleted/unpublished while it sat
        // in the cart, which would 500 the storefront. Prune those lines first,
        // then calculate on a cart with a fresh `lines` relation.
        $cart = CartSession::current(calculate: false);

        if ($this->pruneMissingLines($cart)) {
            $cart->load('lines');
        }

        return $cart->calculate();
    }

    /**
     * Remove cart lines whose variant no longer exists. Returns true if any
     * line was removed.
     */
    protected function pruneMissingLines(Cart $cart): bool
    {
        $missing = $cart->lines()
            ->where('purchasable_type', (new ProductVariant)->getMorphClass())
            ->whereNotIn('purchasable_id', ProductVariant::query()->select('id'))
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
            'lines.purchasable.product.variants',
            'lines.purchasable.product.thumbnail',
        );

        return $cart->lines
            ->map(fn (CartLine $line) => $line->purchasable?->product)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Add a variant to the cart.
     *
     * Purchasability is decided through the `product.purchasable` hook so other
     * modules (Inventory) can veto an oversell before the line is created. The
     * default is Lunar's own stock/backorder check.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function add(int $variantId, int $quantity = 1): Cart
    {
        $variant = ProductVariant::findOrFail($variantId);

        $purchasable = \Modules\Platform\Facades\Hook::applyFilters(
            \Modules\Platform\Support\Hooks::PRODUCT_PURCHASABLE,
            $variant->canBeFulfilledAtQuantity($quantity),
            [$variant, $quantity],
        );

        if (! $purchasable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quantity' => 'Sorry, there isn\'t enough stock to add that quantity.',
            ]);
        }

        $cart = $this->current()->add($variant, $quantity)->calculate();

        \Modules\Platform\Facades\Hook::doAction(
            \Modules\Platform\Support\Hooks::CART_LINE_ADDED,
            [$cart, $variant, $quantity],
        );

        return $cart;
    }

    /**
     * Update a line's quantity.
     */
    public function updateLine(int $lineId, int $quantity): Cart
    {
        $cart = $this->current()->updateLine($lineId, $quantity)->calculate();

        \Modules\Platform\Facades\Hook::doAction(
            \Modules\Platform\Support\Hooks::CART_LINE_UPDATED,
            [$cart, $lineId, $quantity],
        );

        return $cart;
    }

    /**
     * Remove a line.
     */
    public function remove(int $lineId): Cart
    {
        $cart = $this->current()->remove($lineId)->calculate();

        \Modules\Platform\Facades\Hook::doAction(
            \Modules\Platform\Support\Hooks::CART_LINE_REMOVED,
            [$cart, $lineId],
        );

        return $cart;
    }

    /**
     * Forget the current cart from the session (e.g. after an order is placed).
     */
    public function forget(): void
    {
        CartSession::forget();

        \Modules\Platform\Facades\Hook::doAction(
            \Modules\Platform\Support\Hooks::CART_EMPTIED,
        );
    }

    /**
     * Apply a coupon code to the cart (Lunar resolves the matching discount).
     * Validates the code exists + is active/usable before applying; throws a
     * ValidationException with a clear message otherwise.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function applyCoupon(string $code): Cart
    {
        $code = strtoupper(trim($code));

        $discount = \Lunar\Models\Discount::query()
            ->whereRaw('UPPER(coupon) = ?', [$code])
            ->active()
            ->usable()
            ->first();

        if (! $discount) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => 'This coupon code is invalid or has expired.',
            ]);
        }

        $cart = $this->current();
        $cart->update(['coupon_code' => $code]);
        $cart = $this->current()->fresh()->calculate();

        // The code is valid, but it may not apply to this cart's contents
        // (e.g. minimum spend / product restrictions). Surface that clearly.
        if (blank($cart->discountTotal) || $cart->discountTotal->value <= 0) {
            $cart->update(['coupon_code' => null]);

            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => 'This coupon does not apply to the items in your cart.',
            ]);
        }

        \Modules\Platform\Facades\Hook::doAction(
            \Modules\Platform\Support\Hooks::CART_COUPON_APPLIED,
            [$cart, $code],
        );

        return $cart;
    }

    /**
     * Remove the coupon from the cart.
     */
    public function removeCoupon(): Cart
    {
        $this->current()->update(['coupon_code' => null]);

        return $this->current()->fresh()->calculate();
    }
}
