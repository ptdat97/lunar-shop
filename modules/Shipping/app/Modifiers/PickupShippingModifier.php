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
use Modules\Shipping\Services\PickupLocation;

/**
 * Adds "collect at the counter" to the shipping manifest, priced at zero.
 *
 * Kept apart from {@see FlatRateShippingModifier} on purpose: that one resolves
 * a rate from the DB-backed zones and always offers an option, while this one
 * offers nothing at all unless the shop has configured a counter. Folding them
 * together would mean one class with two unrelated reasons to bail out.
 *
 * The free-shipping threshold never applies here — the price is already zero and
 * there is no carrier leg to discount.
 */
class PickupShippingModifier extends ShippingModifier
{
    public function __construct(private readonly PickupLocation $pickup) {}

    public function handle(Cart $cart, Closure $next)
    {
        if (! $this->pickup->isAvailable()) {
            return $next($cart);
        }

        $currency = $cart->currency ?? Currency::getDefault();

        ShippingManifest::addOption(new ShippingOption(
            name: __('storefront.checkout.pickup_name'),
            description: $this->pickup->addressLine(),
            identifier: PickupLocation::IDENTIFIER,
            price: new Price(0, $currency, 1),
            taxClass: TaxClass::getDefault(),
        ));

        return $next($cart);
    }
}
