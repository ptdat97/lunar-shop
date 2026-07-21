<?php

namespace Modules\Shipping\Services;

use Lunar\Models\Cart;
use Lunar\Models\Country;
use Modules\Shipping\Models\ShippingZone;

/**
 * Resolves the shipping rate for a cart from the DB-backed shipping zones,
 * falling back to the static config/shipping.php rate when no zone matches (or
 * none are configured) — so the storefront always has a shipping option.
 */
class ShippingZoneResolver
{
    /**
     * The effective shipping rate (minor units) for a cart, honouring the
     * matching zone's free-shipping threshold.
     */
    public function rateForCart(Cart $cart): int
    {
        $subTotal = $cart->subTotal?->value ?? 0;
        $zone = $this->zoneForCart($cart);

        if ($zone !== null) {
            return $zone->rateFor($subTotal);
        }

        // Fallback: admin-configurable flat rate + threshold (Settings → config).
        $settings = app(\Modules\Core\Support\Settings::class);
        $threshold = (int) $settings->get('shipping.free_threshold', 0);

        return ($threshold > 0 && $subTotal >= $threshold)
            ? 0
            : (int) $settings->get('shipping.standard_rate', 3000);
    }

    /**
     * The most specific enabled zone covering the cart's shipping address, or
     * null when none match. State-scoped zones beat country-wide ones; ties go
     * to the lower priority value.
     */
    public function zoneForCart(Cart $cart): ?ShippingZone
    {
        $address = $cart->shippingAddress;

        if ($address === null) {
            return null;
        }

        $countryCode = $this->countryCode($address->country_id);
        $state = $address->state;

        return ShippingZone::enabled()
            ->where('country_code', $countryCode)
            ->get()
            ->filter(fn (ShippingZone $zone) => $zone->covers($countryCode, $state))
            ->sortBy([
                fn (ShippingZone $a, ShippingZone $b) => $b->specificity() <=> $a->specificity(),
                fn (ShippingZone $a, ShippingZone $b) => $a->priority <=> $b->priority,
            ])
            ->first();
    }

    /** Resolve a country_id to its ISO alpha-2 code. */
    protected function countryCode(?int $countryId): ?string
    {
        if ($countryId === null) {
            return null;
        }

        return Country::find($countryId)?->iso2;
    }
}
