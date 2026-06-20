<?php

namespace Modules\Cart\Services;

use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\ProductVariant;

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
     * Add a variant to the cart.
     */
    public function add(int $variantId, int $quantity = 1): Cart
    {
        $variant = ProductVariant::findOrFail($variantId);

        return $this->current()->add($variant, $quantity)->calculate();
    }

    /**
     * Update a line's quantity.
     */
    public function updateLine(int $lineId, int $quantity): Cart
    {
        return $this->current()->updateLine($lineId, $quantity)->calculate();
    }

    /**
     * Remove a line.
     */
    public function remove(int $lineId): Cart
    {
        return $this->current()->remove($lineId)->calculate();
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
