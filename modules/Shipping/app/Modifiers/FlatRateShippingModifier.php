<?php

namespace Modules\Shipping\Modifiers;

use Closure;
use Lunar\Core\DataTypes\Price;
use Lunar\Core\DataTypes\ShippingOption;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Contracts\Cart;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Modifiers\ShippingModifier;
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
