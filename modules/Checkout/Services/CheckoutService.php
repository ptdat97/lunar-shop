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

    /** Payment method identifiers built into the app. */
    protected const DEFAULT_PAYMENT_METHODS = ['cod', 'bank-transfer', 'vnpay'];

    /**
     * Available shipping options for the current cart.
     */
    public function shippingOptions(): Collection
    {
        return ShippingManifest::getOptions($this->carts->current());
    }

    /**
     * The payment method identifiers offered at checkout — the single source for
     * both the storefront and API validation rules. Passed through the
     * `checkout.payment_methods` filter so a plugin can add a gateway without
     * editing core (it appends its identifier; the built-ins stay the default).
     *
     * @return list<string>
     */
    public function paymentMethods(): array
    {
        return array_values(array_unique(
            \Modules\Hook\Facades\Hook::applyFilters(
                \Modules\Hook\Support\Hooks::CHECKOUT_PAYMENT_METHODS,
                self::DEFAULT_PAYMENT_METHODS,
            )
        ));
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

        // Lunar's setShippingAddress replaces the address row, which drops any
        // previously chosen shipping option (stored on that row). Remember it so
        // re-saving the address (e.g. the user edits it after picking shipping)
        // doesn't silently clear the selection → "Missing Shipping Option" at
        // order time.
        $previousOption = $cart->shippingAddress?->shipping_option;

        // VN 2-tier addresses (state=province, city=ward) carry no postcode, but
        // Lunar requires one for order creation — default it so checkout works.
        $shipping = $this->withPostcode($shipping);

        $cart->setShippingAddress($shipping);
        $cart->setBillingAddress($billing ? $this->withPostcode($billing) : $shipping);

        $cart = $cart->calculate();

        // Re-apply the prior shipping option if it's still available for the new
        // address.
        if ($previousOption && ShippingManifest::getOption($cart, $previousOption)) {
            $cart = $this->setShipping($previousOption);
        }

        \Modules\Hook\Facades\Hook::doAction(
            \Modules\Hook\Support\Hooks::CHECKOUT_ADDRESS_SET,
            [$cart],
        );

        return $cart;
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

        $cart = $cart->setShippingOption($option)->calculate();

        \Modules\Hook\Facades\Hook::doAction(
            \Modules\Hook\Support\Hooks::CHECKOUT_SHIPPING_SELECTED,
            [$cart, $identifier],
        );

        return $cart;
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

        // Guard the common recoverable cause of order-creation failure with a
        // clear message (otherwise Lunar throws a CartException → generic 500).
        if ($cart->isShippable() && ! $cart->getShippingOption()) {
            abort(422, 'Please choose a shipping method before placing your order.');
        }

        $authorize = Payments::driver(
            config("lunar.payments.types.{$paymentType}.driver", 'offline')
        )->cart($cart)->withData([
            'authorized' => config("lunar.payments.types.{$paymentType}.authorized"),
        ])->authorize();

        abort_unless($authorize->success, 422, 'Payment could not be authorized.');

        // Cart is consumed; forget it from the session so a fresh one starts.
        $this->carts->forget();

        $order = Order::findOrFail($authorize->orderId);

        // Broadcast the placement on the shared hook so any module can react
        // (analytics, fulfilment, notifications) without Checkout depending on
        // them. Stock is already reserved by the DecrementStock pipeline.
        \Modules\Hook\Facades\Hook::doAction(
            \Modules\Hook\Support\Hooks::ORDER_PLACED,
            [$order],
        );

        return $order;
    }
}
