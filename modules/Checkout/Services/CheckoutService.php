<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Lunar\Facades\Payments;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\Order;
use Modules\Cart\Services\CartService;
use Modules\Customer\Services\CustomerResolver;

/**
 * Orchestrates checkout over Lunar's engine (addresses → shipping → payment →
 * order). Every step delegates to Lunar — nothing reimplemented.
 */
class CheckoutService
{
    public function __construct(
        protected CartService $carts,
        protected CustomerResolver $customers,
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

        // VN 2-tier addresses (state=province, city=ward) carry no postcode, but
        // Lunar requires one for order creation — default it so checkout works.
        $shipping = $this->withPostcode($shipping);

        $cart->setShippingAddress($shipping);
        $cart->setBillingAddress($billing ? $this->withPostcode($billing) : $shipping);

        return $cart->calculate();
    }

    /**
     * Ensure an address has a non-empty postcode (Lunar requires it).
     *
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    protected function withPostcode(array $address): array
    {
        if (blank($address['postcode'] ?? null)) {
            $address['postcode'] = '00000';
        }

        return $address;
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
        $cart = $this->carts->current();

        // Link the cart to the logged-in user's customer so the order shows up
        // in their order history (guests place orders without a customer).
        if (Auth::check() && ! $cart->customer_id) {
            $customer = $this->customers->forUser(Auth::user());
            $cart->update(['customer_id' => $customer->id]);
        }

        $cart = $cart->calculate();

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
