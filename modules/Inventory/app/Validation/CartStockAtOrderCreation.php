<?php

namespace Modules\Inventory\Validation;

use Lunar\Core\Models\CartLine;
use Lunar\Core\Validation\BaseValidator;

/**
 * Last-line oversell guard: refuse an order whose lines no longer have the
 * stock they were added with.
 *
 * The cart already checks stock when a line is added or changed, but a cart can
 * sit for hours — the units it is holding may be gone by the time checkout is
 * submitted. Lunar's own `ValidateCartForOrderCreation` checks that each line's
 * purchasable is still PURCHASABLE (enabled, product published) but not that it
 * is still AVAILABLE at the line's quantity, so without this a concurrent sale
 * of the last units goes through and the shop oversells.
 *
 * This used to be a whole order-creation pipeline stage that also wrote the
 * stock commitment. The commitment is Lunar's job now (`OrderPlaced` →
 * `SyncStockForOrder`), so all that is left is the check — and it belongs on
 * Lunar's own `order_create` validator hook rather than in the pipeline.
 */
class CartStockAtOrderCreation extends BaseValidator
{
    public function validate(): bool
    {
        $cart = $this->parameters['cart'];

        $short = $cart->lines->filter(function (CartLine $line) {
            $purchasable = $line->purchasable;

            // A missing or non-stocked purchasable is somebody else's check:
            // Lunar's validator already refuses the first, and a purchasable
            // that does not track stock cannot be short of it.
            return $purchasable
                && ! $purchasable->canBeFulfilledAtQuantity((int) $line->quantity);
        });

        if ($short->isEmpty()) {
            return true;
        }

        // Names the items so the shopper can act, rather than a bare refusal.
        $items = $short->map(fn (CartLine $line) => $line->purchasable->getIdentifier())->implode(', ');

        return $this->fail('cart', "Sorry, there isn't enough stock left for: {$items}.");
    }
}
