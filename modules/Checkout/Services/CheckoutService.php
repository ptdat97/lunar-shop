<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Collection;
use Lunar\Facades\Payments;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\Order;
use Modules\Cart\Services\CartService;

/**
 * Orchestrates checkout over Lunar's engine (addresses → shipping → payment →
 * order). Every step delegates to Lunar — nothing reimplemented.
 */
class CheckoutService
{
    public function __construct(
        protected CartService $carts,
    ) {}

    /**
     * Available shipping options for the current cart.
     */
    public function shippingOptions(): Collection
    {
        return ShippingManifest::getOptions($this->carts->current());
    }

    /**
     * Set shipping + billing addresses on the cart.
     *
     * @param  array<string, mixed>  $shipping
     * @param  array<string, mixed>|null  $billing  defaults to shipping
     */
    public function setAddresses(array $shipping, ?array $billing = null): Cart
    {
        $cart = $this->carts->current();

        $cart->setShippingAddress($shipping);
        $cart->setBillingAddress($billing ?? $shipping);

        return $cart->calculate();
    }

    /**
     * Choose a shipping option by identifier.
     */
    public function setShipping(string $identifier): Cart
    {
        $cart = $this->carts->current();

        $option = ShippingManifest::getOption($cart, $identifier);

        abort_if($option === null, 422, "Unknown shipping option [{$identifier}].");

        return $cart->setShippingOption($option)->calculate();
    }

    /**
     * Authorize payment for the given type and create the order.
     */
    public function placeOrder(string $paymentType = 'cod'): Order
    {
        $cart = $this->carts->current()->calculate();

        $authorize = Payments::driver(
            config("lunar.payments.types.{$paymentType}.driver", 'offline')
        )->cart($cart)->withData([
            'authorized' => config("lunar.payments.types.{$paymentType}.authorized"),
        ])->authorize();

        abort_unless($authorize->success, 422, 'Payment could not be authorized.');

        // Cart is consumed; forget it from the session so a fresh one starts.
        $this->carts->forget();

        return Order::findOrFail($authorize->orderId);
    }
}
