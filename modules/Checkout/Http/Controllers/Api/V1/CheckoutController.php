<?php

namespace Modules\Checkout\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cart\Http\Resources\CartResource;
use Modules\Checkout\Http\Resources\OrderResource;
use Modules\Checkout\Services\CheckoutService;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
    ) {}

    /**
     * GET /api/v1/checkout/shipping-options
     */
    public function shippingOptions(): JsonResponse
    {
        $options = $this->checkout->shippingOptions()->map(fn ($o) => [
            'identifier' => $o->identifier,
            'name' => $o->name,
            'description' => $o->description,
            'price' => (string) $o->price->formatted(),
        ])->values();

        return response()->json(['data' => $options]);
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
            'shipping.city' => ['required', 'string', 'max:255'],
            'shipping.postcode' => ['required', 'string', 'max:32'],
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
     */
    public function place(Request $request): OrderResource
    {
        $data = $request->validate([
            'payment_type' => ['nullable', 'string', 'in:cod,bank-transfer,vnpay'],
        ]);

        $order = $this->checkout->placeOrder($data['payment_type'] ?? 'cod');

        return new OrderResource($order->load('lines'));
    }
}
