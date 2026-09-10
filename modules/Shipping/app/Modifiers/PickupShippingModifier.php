<?php

namespace Modules\Shipping\Modifiers;

use Closure;
use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\DataTypes\ShippingOption;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Modifiers\ShippingModifier;
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
            price: new PriceValue(0, $currency),
            taxClass: TaxClass::getDefault(),
            // Lunar's own flag for "the customer comes and gets it". CreateOrder
            // stamps it onto the shipping line's meta, and the Collection
            // fulfilment method claims the order's lines from there instead of
            // the Shipping one. Left at its default `false`, a pickup order
            // arrived in the panel as a parcel to send: staff were shown ship
            // and add-tracking actions for someone walking into the shop.
            collect: true,
        ));

        return $next($cart);
    }
}
