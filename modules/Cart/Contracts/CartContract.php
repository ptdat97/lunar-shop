<?php

namespace Modules\Cart\Contracts;

use Illuminate\Support\Collection;
use Lunar\Models\Cart;

/**
 * The cart service contract — the single entry point web + API resolve for cart
 * state (wraps Lunar's CartSession). Extracted so it can be decorated (gift-wrap
 * fees, line rules) without editing the service. Mirrors CartService's public
 * API exactly; binding stays the existing class.
 */
interface CartContract
{
    public function current(): Cart;

    /** @return Collection<int, \Lunar\Models\Product> */
    public function products(): Collection;

    public function add(int $variantId, int $quantity = 1): Cart;

    public function updateLine(int $lineId, int $quantity): Cart;

    public function remove(int $lineId): Cart;

    public function forget(): void;

    public function applyCoupon(string $code): Cart;

    public function removeCoupon(): Cart;
}
