<?php

namespace Modules\Checkout\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Checkout\Http\Resources\CartResource;
use Modules\Checkout\Http\Resources\ShippingOptionResource;
use Modules\Checkout\Services\CheckoutService;
use Modules\Order\Http\Resources\OrderResource;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
    ) {}

    /**
     * GET /api/v1/checkout/shipping-options
     */
    public function shippingOptions(): AnonymousResourceCollection
    {
        return ShippingOptionResource::collection(
            $this->checkout->shippingOptions()->values()
        );
    }

    /**
     * POST /api/v1/checkout/pickup
     *
     * The collect-at-counter counterpart of `addresses`: contact details only,
     * because the destination is the shop. Picks the pickup shipping option in
     * the same call so the two cannot disagree.
     */
    public function pickup(Request $request): CartResource
    {
        $contact = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'integer'],
            'contact_email' => ['nullable', 'email'],
            // Not nullable like the delivery form: with no address to go on, the
            // phone is the only way the counter can reach whoever is collecting.
            'contact_phone' => ['required', 'string', 'max:32'],
        ]);

        return new CartResource($this->checkout->setPickup($contact));
    }

    /**
     * POST /api/v1/checkout/addresses
     */
    public function addresses(Request $request): CartResource
    {
        $shipping = $request->validate([
            'shipping.first_name' => ['required', 'string', 'max:255'],
            'shipping.last_name' => ['required', 'string', 'max:255'],
            'shipping.line_one' => ['required', 'string', 'max:255'],
            'shipping.state' => ['required', 'string', 'max:255'],   // Tỉnh/Thành
            'shipping.city' => ['required', 'string', 'max:255'],    // Phường/Xã
            'shipping.postcode' => ['nullable', 'string', 'max:32'],
            'shipping.country_id' => ['required', 'integer'],
            'shipping.contact_email' => ['nullable', 'email'],
            'shipping.contact_phone' => ['nullable', 'string', 'max:32'],
        ])['shipping'];

        $cart = $this->checkout->setAddresses($shipping);

        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }

    /**
     * POST /api/v1/checkout/shipping  { identifier }
     */
    public function shipping(Request $request): CartResource
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
        ]);

        $cart = $this->checkout->setShipping($data['identifier']);

        return new CartResource($cart->loadMissing('lines.purchasable.product'));
    }

    /**
     * POST /api/v1/checkout  { payment_type }
     *
     * Returns the Order module's OrderResource — one order contract for the
     * whole API, so this and GET /api/v1/orders/{id} give clients the same shape.
     */
    public function place(Request $request): OrderResource
    {
        $data = $request->validate([
            'payment_type' => ['nullable', 'string', Rule::in($this->checkout->paymentMethods())],
            // Không bắt buộc: client hiện có chưa gửi. Gửi thì được kiểm.
            'fingerprint' => ['nullable', 'string', 'max:64'],
        ]);

        $order = $this->checkout->placeOrder($data['payment_type'] ?? 'cod', $data['fingerprint'] ?? null);

        // Eager-load what OrderResource exposes; its whenLoaded() guards would
        // otherwise silently drop the addresses from the placed-order payload.
        return new OrderResource($order->load(['lines', 'shippingAddress', 'billingAddress']));
    }
}
