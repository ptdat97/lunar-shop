<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lunar\Core\Exceptions\Carts\CartException;
use Lunar\Core\Facades\Payments;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Order;
use Modules\Core\Support\Settings;
use Modules\Customer\Services\CustomerResolver;
use Modules\Shipping\Services\PickupLocation;

/**
 * Orchestrates checkout over Lunar's engine (addresses → shipping → payment →
 * order). Every step delegates to Lunar — nothing reimplemented.
 */
class CheckoutService
{
    public function __construct(
        protected CartService $carts,
        protected CustomerResolver $customers,
        protected Settings $settings,
        protected PickupLocation $pickup,
    ) {}

    /** Payment method identifiers built into the app. */
    protected const DEFAULT_PAYMENT_METHODS = ['cod', 'bank-transfer', 'vnpay', 'momo'];

    /**
     * Online gateways, mapped to the setting whose presence means "this gateway
     * is set up". Offline methods (cod, bank-transfer) need no credentials and
     * are always on.
     */
    protected const GATEWAY_KEYS = [
        'vnpay' => 'payment.vnpay.tmn_code',
        'momo' => 'payment.momo.partner_code',
    ];

    /**
     * Available shipping options for the current cart.
     */
    public function shippingOptions(): Collection
    {
        return ShippingManifest::getOptions($this->carts->current());
    }

    /**
     * The payment method identifiers offered at checkout — the single source for
     * both the storefront and API validation rules.
     *
     * A gateway with no credentials is NOT offered. This used to return the
     * built-in list unfiltered while `paymentContext()` computed enablement
     * separately for the UI, so the two disagreed: the checkout page correctly
     * hid an unconfigured VNPay, but the API happily accepted
     * `payment_type: vnpay` for it. The order was then placed with no payment
     * step at all — `paymentRedirectUrl()` returns null for an unconfigured
     * gateway, so the shopper landed on the confirmation page having paid
     * nothing, and the stock sat committed until the abandoned-order sweep.
     *
     * @return list<string>
     */
    public function paymentMethods(): array
    {
        return array_values(array_filter(
            self::DEFAULT_PAYMENT_METHODS,
            fn (string $method) => $this->gatewayIsUsable($method),
        ));
    }

    /**
     * Whether a method can actually take money right now. Offline methods
     * always can; a gateway needs its credentials.
     */
    protected function gatewayIsUsable(string $method): bool
    {
        $settingKey = self::GATEWAY_KEYS[$method] ?? null;

        return $settingKey === null || filled($this->settings->get($settingKey));
    }

    /**
     * Payment context for the checkout page: which online gateways are enabled
     * (a gateway with no primary key configured is off) and the pre-selected
     * method — single source for the SSR form, so controllers/Blade never read
     * Settings themselves (standards §3/§7).
     *
     * `pickup` is null when the shop offers no counter collection — one thing for
     * the storefront to check before it decides whether to show the choice and
     * hide the delivery-address step.
     *
     * @return array{vnpayEnabled: bool, momoEnabled: bool, defaultPayment: string, pickup: array<string, string>|null}
     */
    public function paymentContext(): array
    {
        return [
            // Same predicate as paymentMethods(), not a second copy of it.
            'vnpayEnabled' => $this->gatewayIsUsable('vnpay'),
            'momoEnabled' => $this->gatewayIsUsable('momo'),
            'defaultPayment' => (string) $this->settings->get('payment.default', 'cod'),
            'pickup' => $this->pickup->toArray(),
        ];
    }

    /**
     * Where an online gateway takes the shopper after the order is placed
     * (VNPay/MoMo hosted payment page). Null for offline methods (cod/bank) or
     * an unconfigured gateway → go straight to the confirmation page.
     *
     * @throws \Throwable MoMo create-payment failures bubble up (caller decides UX).
     */
    public function paymentRedirectUrl(Order $order, string $paymentType, string $ip): ?string
    {
        if ($paymentType === 'vnpay') {
            $gateway = VNPayGateway::fromConfig();

            return $gateway->isConfigured() ? $gateway->buildPaymentUrl($order, $ip) : null;
        }

        if ($paymentType === 'momo') {
            $gateway = MoMoGateway::fromConfig();

            return $gateway->isConfigured() ? $gateway->createPayment($order) : null;
        }

        return null;
    }

    /**
     * Stamp the shopper's current language onto the cart.
     *
     * Written with `withoutTimestamps` so it cannot disturb `updated_at`: the
     * abandoned-cart sweep measures staleness from that column, and a write
     * here would make every cart look freshly active.
     */
    protected function rememberLocale(Cart $cart): void
    {
        $meta = (array) ($cart->meta ?? []);

        if (($meta['locale'] ?? null) === app()->getLocale()) {
            return;
        }

        $meta['locale'] = app()->getLocale();

        Cart::withoutTimestamps(fn () => $cart->forceFill(['meta' => $meta])->saveQuietly());
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

        // Ghi ngôn ngữ khách đang xem vào giỏ. Đây là điểm duy nhất biết chắc
        // khách đang thanh toán VÀ vẫn còn trong một request (có locale thật).
        // Email nhắc giỏ bỏ quên chạy từ cron, nơi locale là mặc định của app
        // chứ không phải của khách — không có dấu vết này thì lời nhắc luôn gửi
        // bằng ngôn ngữ mặc định, kể cả cho khách đang xem tiếng Anh.
        $this->rememberLocale($cart);

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

        return $cart;
    }

    /**
     * Collect at the counter: the customer gives contact details only.
     *
     * Lunar writes a shipping address row on every order, so this cannot simply
     * skip the address — it fills the destination with the shop's own while
     * keeping the customer's name and phone, so the counter knows who is coming.
     * The order then travels the ordinary OrderStatus flow, shipping line and
     * all, priced at zero.
     *
     * Selecting the option is part of the same call on purpose: an order that
     * carries the store's address but a courier shipping option would quietly
     * ship to the shop itself.
     *
     * @param  array<string, mixed>  $contact
     *
     * @throws ValidationException
     */
    public function setPickup(array $contact): Cart
    {
        if (! $this->pickup->isAvailable()) {
            throw ValidationException::withMessages([
                'shipping' => __('storefront.checkout.pickup_unavailable'),
            ]);
        }

        $cart = $this->setAddresses($this->pickup->asShippingAddress($contact));

        // setAddresses re-applies whatever option was chosen before; force the
        // pickup one so the two can never disagree.
        return $this->setShipping(PickupLocation::IDENTIFIER);
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
     *
     * Serialised per cart with a cache lock: two concurrent "Place order"
     * requests for the same cart (double-submit, a retried gateway callback,
     * two tabs) must not both pass the empty-cart check and each create an order
     * — that reserved stock twice and charged twice. The lock makes the second
     * request wait, and by the time it runs the first has consumed the cart
     * (forget()), so it hits the empty-cart guard with a clean 422.
     */
    public function placeOrder(string $paymentType = 'cod'): Order
    {
        $cart = $this->carts->current();

        // Nothing to lock/place if there's no cart yet.
        if ($cart->lines->isEmpty()) {
            abort(422, 'Your cart is empty.');
        }

        $lock = Cache::lock("checkout:place:cart:{$cart->id}", 15);

        // block() waits up to 10s for a concurrent placement to finish rather
        // than erroring immediately; the loser then sees the consumed cart.
        return $lock->block(10, fn () => $this->doPlaceOrder($paymentType));
    }

    /**
     * The actual place flow, run while holding the per-cart lock.
     */
    protected function doPlaceOrder(string $paymentType): Order
    {
        // Re-read the cart INSIDE the lock: a request that was blocked waiting
        // for the winner now sees the empty cart the winner left behind.
        $cart = $this->carts->current();

        if ($cart->lines->isEmpty()) {
            abort(422, 'Your cart is empty.');
        }

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

        // `meta.payment_type` is recorded for EVERY order, not just the
        // gateways. Since Lunar 2.0 the lifecycle status is derived, and the one
        // thing the derived facts cannot tell apart is "pays on delivery" from
        // "has not paid the gateway yet" — both are payment-pending. The payment
        // type is what separates them (see OrderStatus::isPaidOnDelivery), so an
        // order without one would be unclassifiable.
        // Lunar refuses an uncreatable cart by throwing, which would surface as
        // a 500 — the worst possible answer to "somebody bought the last one
        // while you were deciding". Every reason it throws for is something the
        // shopper can act on, so it is a 422 with the reason kept.
        try {
            $authorize = Payments::driver(
                config("lunar.payments.types.{$paymentType}.driver", 'offline')
            )->cart($cart)->withData([
                'authorized' => config("lunar.payments.types.{$paymentType}.authorized"),
                // `locale` đi kèm `payment_type` vì cùng một lý do: email gửi
                // SAU này (xin đánh giá, chạy từ cron) không còn request nào để
                // đọc ngôn ngữ của khách.
                'meta' => ['payment_type' => $paymentType, 'locale' => app()->getLocale()],
            ])->authorize();
        } catch (CartException $e) {
            abort(422, $e->getMessage());
        }

        abort_unless($authorize->success, 422, 'Payment could not be authorized.');

        // Cart is consumed; forget it from the session so a fresh one starts.
        $this->carts->forget();

        return Order::findOrFail($authorize->orderId);
    }
}
