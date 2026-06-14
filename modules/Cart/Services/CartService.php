<?php

namespace Modules\Cart\Services;

use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
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
        $cart = CartSession::current() ?? CartSession::manager()->getCart();

        return $cart->calculate();
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
}
