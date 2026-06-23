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
use Modules\Shipping\Services\ShippingZoneResolver;

/**
 * Registers shipping options into Lunar's manifest before totals are calculated.
 * The rate comes from the DB-backed shipping zones (matched on the cart's
 * shipping address), falling back to the static config/shipping.php flat rate
 * when no zone matches.
 *
 * This is Lunar's official extension point — we inherit, not reimplement.
 */
class FlatRateShippingModifier extends ShippingModifier
{
    public function handle(Cart $cart, Closure $next)
    {
        $currency = $cart->currency ?? Currency::getDefault();
        $taxClass = TaxClass::getDefault();

        $rate = app(ShippingZoneResolver::class)->rateForCart($cart);

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
