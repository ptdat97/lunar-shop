<?php

namespace Modules\Shipping\Modifiers;

use Closure;
use Lunar\Base\ShippingModifier;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;

/**
 * Registers shipping options into Lunar's manifest before totals are calculated.
 * Phase 1: flat standard rate, free over an optional threshold.
 *
 * This is Lunar's official extension point — we inherit, not reimplement.
 */
class FlatRateShippingModifier extends ShippingModifier
{
    public function handle(Cart $cart, Closure $next)
    {
        $currency = $cart->currency ?? Currency::getDefault();
        $taxClass = TaxClass::getDefault();

        $subTotal = $cart->subTotal?->value ?? 0;
        $freeThreshold = (int) config('shipping.free_threshold', 0);

        $rate = ($freeThreshold > 0 && $subTotal >= $freeThreshold)
            ? 0
            : (int) config('shipping.standard_rate', 3000);

        ShippingManifest::addOption(new ShippingOption(
            name: 'Standard Delivery',
            description: 'Delivered in 3–5 business days',
            identifier: 'standard',
            price: new Price($rate, $currency, 1),
            taxClass: $taxClass,
        ));

        return $next($cart);
    }
}
