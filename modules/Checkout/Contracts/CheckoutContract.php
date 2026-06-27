<?php

namespace Modules\Checkout\Contracts;

use Illuminate\Support\Collection;
use Lunar\Models\Cart;
use Lunar\Models\Order;

/**
 * The checkout service contract — orchestrates Lunar's checkout (addresses →
 * shipping → payment → order). Extracted so it can be decorated (fraud check,
 * extra validation) without editing the service. Mirrors CheckoutService's
 * public API exactly; binding stays the existing class.
 */
interface CheckoutContract
{
    public function shippingOptions(): Collection;

    /** @return list<string> */
    public function paymentMethods(): array;

    /**
     * @param  array<string, mixed>  $shipping
     * @param  array<string, mixed>|null  $billing
     */
    public function setAddresses(array $shipping, ?array $billing = null): Cart;

    public function setShipping(string $identifier): Cart;

    public function placeOrder(string $paymentType = 'cod'): Order;
}
