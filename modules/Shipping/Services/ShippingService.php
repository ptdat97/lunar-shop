<?php

namespace Modules\Shipping\Services;

use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;

class ShippingService
{
    /**
     * Get available shipping options for a cart.
     */
    public function options(Cart $cart): array
    {
        return ShippingManifest::getOptions($cart)->toArray();
    }

    /**
     * Get a specific shipping option by identifier.
     */
    public function option(Cart $cart, string $identifier): ?object
    {
        return ShippingManifest::getOption($cart, $identifier);
    }

    /**
     * Calculate shipping for a cart with the given method.
     */
    public function calculate(Cart $cart, string $identifier): Cart
    {
        $option = $this->option($cart, $identifier);

        abort_if($option === null, 422, "Unknown shipping option [{$identifier}].");

        return $cart->setShippingOption($option)->calculate();
    }
}